#!/usr/bin/env node

"use strict";

const { execSync } = require("node:child_process");

const scenario = process.argv[2] || "redeem-race";
const baseUrl = process.env.BASE_URL || "http://localhost:8000/api/v1";
const memberId = Number(process.env.MEMBER_ID || "1");
const giftId = Number(process.env.GIFT_ID || "4");
const amount = process.env.AMOUNT || "100000.00";
const concurrency = Number(process.env.CONCURRENCY || "10");
const rounds = Number(process.env.ROUNDS || "5");
const timeoutMs = Number(process.env.TIMEOUT_MS || "10000");
const reseedCommand = process.env.RESEED_COMMAND || "";

function percentile(values, p) {
  if (values.length === 0) {
    return 0;
  }

  const sorted = [...values].sort((a, b) => a - b);
  const index = Math.min(
    sorted.length - 1,
    Math.max(0, Math.ceil((p / 100) * sorted.length) - 1),
  );

  return sorted[index];
}

async function requestJson(path, payload) {
  const start = performance.now();
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(`${baseUrl}${path}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
      signal: controller.signal,
    });

    const elapsedMs = performance.now() - start;
    let json = null;

    try {
      json = await response.json();
    } catch {
      json = null;
    }

    return {
      ok: response.ok,
      status: response.status,
      elapsedMs,
      body: json,
    };
  } catch (error) {
    return {
      ok: false,
      status: 0,
      elapsedMs: performance.now() - start,
      body: {
        error: error instanceof Error ? error.message : String(error),
      },
    };
  } finally {
    clearTimeout(timer);
  }
}

function printSummary(results) {
  const byStatus = new Map();
  const latencies = [];

  for (const result of results) {
    byStatus.set(result.status, (byStatus.get(result.status) || 0) + 1);
    latencies.push(result.elapsedMs);
  }

  const statusRows = Array.from(byStatus.entries())
    .sort((a, b) => a[0] - b[0])
    .map(([status, count]) => ({ status, count }));

  console.log("--- Summary ---");
  console.table(statusRows);
  console.log(`total_requests=${results.length}`);
  console.log(`latency_ms_p50=${percentile(latencies, 50).toFixed(2)}`);
  console.log(`latency_ms_p95=${percentile(latencies, 95).toFixed(2)}`);
}

async function runRedeemRace() {
  console.log("Scenario: redeem-race");
  console.log(`Target: ${baseUrl}/redemptions`);
  console.log("Expected for stock=1: one 201 and one 409.");

  const requests = [
    requestJson("/redemptions", { member_id: memberId, gift_id: giftId }),
    requestJson("/redemptions", { member_id: memberId, gift_id: giftId }),
  ];

  const results = await Promise.all(requests);
  return results;
}

function evaluateRedeemRace(results) {
  printSummary(results);

  const created = results.filter((r) => r.status === 201).length;
  const conflicts = results.filter((r) => r.status === 409).length;

  if (created === 1 && conflicts === 1) {
    console.log(
      "PASS: race behavior matched expected outcome (1 success, 1 conflict).",
    );
    return true;
  }

  console.log("WARN: race behavior did not match strict expectation.");
  console.log(
    "Tip: re-seed data and ensure target gift has stock=1 and member has enough points.",
  );
  return false;
}

async function runRedeemRaceLoop() {
  console.log("Scenario: redeem-race-loop");
  console.log(`Rounds=${rounds}`);

  if (!reseedCommand) {
    console.log("RESEED_COMMAND is required for redeem-race-loop.");
    process.exit(1);
  }

  let passedRounds = 0;

  for (let round = 1; round <= rounds; round += 1) {
    console.log(`\n--- Round ${round}/${rounds} ---`);
    execSync(reseedCommand, { stdio: "inherit" });

    const results = await runRedeemRace();
    const passed = evaluateRedeemRace(results);
    if (passed) {
      passedRounds += 1;
    }
  }

  console.log(`\nredeem_race_loop_passed=${passedRounds}/${rounds}`);
  if (passedRounds === rounds) {
    console.log("PASS: all rounds matched expected race behavior.");
    process.exit(0);
  }

  console.log("WARN: at least one round failed expected race behavior.");
  process.exit(1);
}

async function runTransactionBurst() {
  console.log("Scenario: transaction-burst");
  console.log(`Target: ${baseUrl}/transactions`);
  console.log(`Rounds=${rounds}, Concurrency=${concurrency}`);

  const allResults = [];

  for (let round = 1; round <= rounds; round += 1) {
    const batch = [];

    for (let i = 0; i < concurrency; i += 1) {
      batch.push(requestJson("/transactions", { member_id: memberId, amount }));
    }

    const roundResults = await Promise.all(batch);
    const successCount = roundResults.filter((r) => r.status === 201).length;

    console.log(
      `Round ${round}: success=${successCount}/${roundResults.length}`,
    );
    allResults.push(...roundResults);
  }

  printSummary(allResults);

  const failed = allResults.filter((r) => r.status !== 201);
  if (failed.length === 0) {
    console.log("PASS: all transaction requests succeeded.");
    process.exit(0);
  }

  console.log(
    `WARN: ${failed.length} requests failed. Inspect summary/status codes above.`,
  );
  process.exit(1);
}

(async () => {
  if (scenario === "redeem-race") {
    const results = await runRedeemRace();
    const passed = evaluateRedeemRace(results);
    process.exit(passed ? 0 : 1);
    return;
  }

  if (scenario === "redeem-race-loop") {
    await runRedeemRaceLoop();
    return;
  }

  if (scenario === "transaction-burst") {
    await runTransactionBurst();
    return;
  }

  console.error(
    "Unknown scenario. Use one of: redeem-race, redeem-race-loop, transaction-burst",
  );
  process.exit(1);
})();
