/**
 * Read load against the API (SRS 45, 51).
 *
 * The shape a real deployment sees on a weekday morning: a lot of people
 * opening lists and a few opening records, all through the API, all inside one
 * company. It exists to answer one question with a number rather than an
 * opinion — is p95 under 500 ms at the *target* column of the capacity table,
 * not at the ceiling (SRS 51 says so explicitly).
 *
 * Run:
 *   k6 run -e BASE_URL=https://staging.example.com \
 *          -e EMAIL=loadtest@example.com -e PASSWORD=... load-tests/browse.js
 *
 * Seed the target first. Measuring an empty database measures nothing: every
 * index looks fast against forty rows, and the queries this is meant to catch
 * are the ones that degrade at twenty thousand assets.
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://localhost:8000';
const EMAIL = __ENV.EMAIL;
const PASSWORD = __ENV.PASSWORD;

const listLatency = new Trend('list_latency', true);
const recordLatency = new Trend('record_latency', true);

export const options = {
    scenarios: {
        // 100 concurrent users per company is the target in SRS 51. Ramped
        // rather than dropped on, so a connection-pool limit shows up as a
        // rising latency curve and not as one wall of errors.
        browsing: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '1m', target: 25 },
                { duration: '2m', target: 100 },
                { duration: '5m', target: 100 },
                { duration: '1m', target: 0 },
            ],
        },
    },
    thresholds: {
        // The requirement, stated as a pass/fail rather than a chart nobody
        // reads afterwards.
        'http_req_duration{expected_response:true}': ['p(95)<500'],
        list_latency: ['p(95)<500'],
        record_latency: ['p(95)<500'],
        http_req_failed: ['rate<0.01'],
    },
};

export function setup() {
    if (!EMAIL || !PASSWORD) {
        throw new Error('Set EMAIL and PASSWORD. A load test that cannot sign in measures the login page.');
    }

    const response = http.post(
        `${BASE}/api/v1/auth/login`,
        JSON.stringify({ email: EMAIL, password: PASSWORD, device_name: 'k6' }),
        { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } },
    );

    check(response, { 'signed in': (r) => r.status === 201 });

    return { token: response.json('data.access_token') };
}

export default function (data) {
    const params = {
        headers: {
            Authorization: `Bearer ${data.token}`,
            Accept: 'application/json',
        },
    };

    // Lists, which is what most of the traffic is. Filtered and sorted the way
    // a person actually uses them rather than as bare index calls, because an
    // unfiltered first page is the one query that is always fast.
    const lists = [
        '/api/v1/assets?status=RUNNING&sort=asset_code&per_page=25',
        '/api/v1/work-orders?open=true&per_page=25',
        '/api/v1/breakdowns?open=true&per_page=25',
        '/api/v1/spare-parts?per_page=25',
    ];

    for (const path of lists) {
        const response = http.get(`${BASE}${path}`, params);

        listLatency.add(response.timings.duration);
        check(response, { 'list ok': (r) => r.status === 200 });
    }

    // One record, followed from a list, which is what a person does next.
    const assets = http.get(`${BASE}/api/v1/assets?per_page=25`, params);
    const first = assets.json('data.0.id');

    if (first) {
        const record = http.get(`${BASE}/api/v1/assets/${first}`, params);

        recordLatency.add(record.timings.duration);
        check(record, { 'record ok': (r) => r.status === 200 });
    }

    // Think time. Without it this measures how fast the server can be hammered
    // by 100 threads, which is a number no deployment will ever experience.
    sleep(Math.random() * 3 + 2);
}
