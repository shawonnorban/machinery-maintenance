/**
 * Write load: meter readings from machines (SRS 51).
 *
 * The capacity table asks for 50,000 meter readings per day per company, with
 * a ceiling of 500,000. Those do not arrive evenly — a dye house posts a batch
 * at shift change — so this is a spike test rather than a steady one.
 *
 * Two things are under test and only one of them is throughput:
 *
 *   1. Does the write path hold up when a controller empties its buffer.
 *   2. Does the idempotency claim behave under contention. Every reading here
 *      is sent twice with the same key, which is what a retrying PLC does, and
 *      the run fails if the second call ever executes rather than replaying.
 *      That is the property the whole mechanism exists for and the one that
 *      only breaks under concurrency.
 *
 * Run:
 *   k6 run -e BASE_URL=https://staging.example.com \
 *          -e CLIENT_ID=cid_... -e CLIENT_SECRET=sk_... \
 *          -e METER_IDS=01ABC,01DEF load-tests/ingest.js
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://localhost:8000';
const CLIENT_ID = __ENV.CLIENT_ID;
const CLIENT_SECRET = __ENV.CLIENT_SECRET;
const METERS = (__ENV.METER_IDS || '').split(',').filter(Boolean);

const duplicated = new Counter('readings_executed_twice');
const replayed = new Counter('readings_replayed');

export const options = {
    scenarios: {
        shiftChange: {
            executor: 'ramping-arrival-rate',
            startRate: 5,
            timeUnit: '1s',
            preAllocatedVUs: 50,
            maxVUs: 200,
            stages: [
                { duration: '30s', target: 5 },
                // The spike: a shift change, where every controller on the
                // floor posts what it has been holding.
                { duration: '30s', target: 120 },
                { duration: '2m', target: 120 },
                { duration: '30s', target: 5 },
            ],
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        'http_req_duration{expected_response:true}': ['p(95)<500'],
        // Not a performance threshold. A single duplicate execution means a
        // reading was counted twice, which brings a service forward and raises
        // a maintenance job that is not due.
        readings_executed_twice: ['count==0'],
    },
};

export function setup() {
    if (!CLIENT_ID || !CLIENT_SECRET || METERS.length === 0) {
        throw new Error('Set CLIENT_ID, CLIENT_SECRET and METER_IDS.');
    }

    const response = http.post(
        `${BASE}/api/v1/auth/token`,
        JSON.stringify({ client_id: CLIENT_ID, client_secret: CLIENT_SECRET }),
        { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } },
    );

    check(response, { 'client signed in': (r) => r.status === 201 });

    return { token: response.json('data.access_token'), start: Date.now() };
}

export default function (data) {
    const meter = METERS[Math.floor(Math.random() * METERS.length)];

    // A cumulative meter never goes down, so the value has to climb. Seconds
    // since the run began is monotonic and unique enough per meter.
    const value = Math.floor((Date.now() - data.start) / 1000) + 100000;

    // Generated once and reused, exactly as the offline queue does: the key
    // belongs to the reading, not to the attempt.
    const key = `k6-${meter}-${value}`;

    const params = {
        headers: {
            Authorization: `Bearer ${data.token}`,
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'Idempotency-Key': key,
        },
    };

    const body = JSON.stringify({ value: String(value), source_reference: key });

    const first = http.post(`${BASE}/api/v1/meters/${meter}/readings`, body, params);
    check(first, { 'reading accepted': (r) => r.status === 201 || r.status === 409 });

    // The retry a real controller makes when the first response is slow.
    const second = http.post(`${BASE}/api/v1/meters/${meter}/readings`, body, params);

    if (second.headers['Idempotent-Replay'] === 'true') {
        replayed.add(1);
    } else if (second.status === 201) {
        // Executed a second time. This is the failure the mechanism exists to
        // prevent, and it is worth failing the whole run over.
        duplicated.add(1);
    }

    sleep(1);
}
