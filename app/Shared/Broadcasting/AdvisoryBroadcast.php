<?php

declare(strict_types=1);

namespace App\Shared\Broadcasting;

/**
 * A live update is advisory, and its queue behaviour should say so.
 *
 * Every broadcast in this product announces something that has *already*
 * happened and is already durable: the stock moved, the breakdown was
 * recorded, the work order was assigned. The websocket message is how a screen
 * finds out sooner than its next refresh, and nothing depends on it arriving.
 *
 * Laravel's defaults treat it like any other job, which is wrong in three
 * ways when the websocket server is unreachable:
 *
 *   - Three attempts with backoff, delivering a "live" update minutes late.
 *     A stale live update is worse than none: it lands after the screen has
 *     already been refreshed and contradicts what is on it.
 *   - Each attempt waits for a connection timeout on the default queue, so a
 *     down websocket server slows down the notifications, webhooks and exports
 *     queued behind it — work that genuinely matters and has nothing to do
 *     with it.
 *   - Three rows in `failed_jobs` per event rather than one, burying the
 *     failures somebody actually needs to see.
 *
 * So: its own queue, drained after the default one; one attempt; a short
 * timeout. A down websocket server then costs exactly one quick failure per
 * event and delays nothing else.
 *
 * The worker must be told about the queue — `--queue=default,broadcasts` —
 * and doc 11 §5 carries that. Named in that order, so real work is always
 * drained first.
 */
trait AdvisoryBroadcast
{
    /** One attempt. A retried live update arrives stale, which helps nobody. */
    public int $tries = 1;

    /**
     * Short. This is a local HTTP call to the websocket server; if it has not
     * answered in five seconds it is not going to, and every second spent
     * waiting is a second the queue is not doing something that matters.
     */
    public int $timeout = 5;

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
