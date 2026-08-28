#!/usr/bin/env python3
"""Silent GitHub Actions watchdog for Canopi deploys.

Default mode prints nothing unless a new completed main-branch deploy exists.
Designed for Hermes cron with no_agent=True: empty stdout means no delivery.
"""

from __future__ import annotations

import json
import os
import sys
import tempfile
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Any

REPO = "elvanrivaldi43-droid/canopi-app"
WORKFLOW = "deploy.yml"
API_ROOT = "https://api.github.com"
STATE_PATH = Path(
    os.environ.get(
        "CANOPI_WATCHDOG_STATE",
        "/root/projects/canopi-app/.hermes/state/github_deploy_watchdog.json",
    )
)
USER_AGENT = "canopi-hermes-deploy-watchdog/1.0"


def api_get(path: str) -> Any:
    request = urllib.request.Request(
        f"{API_ROOT}{path}",
        headers={
            "Accept": "application/vnd.github+json",
            "User-Agent": USER_AGENT,
            "X-GitHub-Api-Version": "2022-11-28",
        },
    )
    with urllib.request.urlopen(request, timeout=20) as response:
        return json.load(response)


def default_state() -> dict[str, Any]:
    return {
        "initialized": False,
        "last_processed_run_id": 0,
        "error_count": 0,
        "error_alerted": False,
    }


def load_state() -> dict[str, Any]:
    state = default_state()
    try:
        loaded = json.loads(STATE_PATH.read_text(encoding="utf-8"))
        if isinstance(loaded, dict):
            state.update(loaded)
    except (FileNotFoundError, json.JSONDecodeError, OSError):
        pass
    return state


def write_state(state: dict[str, Any]) -> None:
    STATE_PATH.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
    fd, temporary_name = tempfile.mkstemp(
        prefix=f".{STATE_PATH.name}.", dir=str(STATE_PATH.parent)
    )
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as handle:
            json.dump(state, handle, ensure_ascii=False, indent=2, sort_keys=True)
            handle.write("\n")
        os.chmod(temporary_name, 0o600)
        os.replace(temporary_name, STATE_PATH)
    finally:
        try:
            os.unlink(temporary_name)
        except FileNotFoundError:
            pass


def workflow_runs() -> list[dict[str, Any]]:
    query = urllib.parse.urlencode(
        {"branch": "main", "event": "push", "per_page": 10}
    )
    payload = api_get(
        f"/repos/{REPO}/actions/workflows/{WORKFLOW}/runs?{query}"
    )
    runs = payload.get("workflow_runs", []) if isinstance(payload, dict) else []
    return [run for run in runs if isinstance(run, dict)]


def jobs_for(run_id: int) -> list[dict[str, Any]]:
    payload = api_get(f"/repos/{REPO}/actions/runs/{run_id}/jobs?per_page=100")
    jobs = payload.get("jobs", []) if isinstance(payload, dict) else []
    return [job for job in jobs if isinstance(job, dict)]


def failure_annotations(check_run_id: int) -> list[str]:
    try:
        payload = api_get(
            f"/repos/{REPO}/check-runs/{check_run_id}/annotations?per_page=100"
        )
    except Exception:
        return []
    if not isinstance(payload, list):
        return []
    messages: list[str] = []
    for annotation in payload:
        if not isinstance(annotation, dict):
            continue
        if annotation.get("annotation_level") != "failure":
            continue
        message = str(annotation.get("message") or "").strip()
        if message and message not in messages:
            messages.append(message)
    return messages[:3]


def is_verify_job(job: dict[str, Any]) -> bool:
    name = str(job.get("name") or "").lower()
    return "verify" in name or "verification" in name or "verifikasi" in name


def is_deploy_job(job: dict[str, Any]) -> bool:
    name = str(job.get("name") or "").lower()
    return "deploy" in name or "ftp" in name


def concise_error(messages: list[str]) -> str:
    if not messages:
        return ""
    cleaned = [" ".join(message.split())[:240] for message in messages]
    return "\nError: " + " | ".join(cleaned)


def classify_run(
    run: dict[str, Any],
    jobs: list[dict[str, Any]],
    annotations: dict[int, list[str]] | None = None,
) -> str:
    annotations = annotations or {}
    conclusion = str(run.get("conclusion") or "unknown")
    verify_jobs = [job for job in jobs if is_verify_job(job)]
    deploy_jobs = [job for job in jobs if is_deploy_job(job)]
    failed_verify = [
        job
        for job in verify_jobs
        if job.get("conclusion") not in ("success", "skipped", None)
    ]
    failed_deploy = [
        job
        for job in deploy_jobs
        if job.get("conclusion") not in ("success", "skipped", None)
    ]

    details: list[str] = []
    for job in failed_verify + failed_deploy:
        details.extend(annotations.get(int(job.get("id") or 0), []))

    if failed_verify:
        headline = "❌ VERIFIKASI GAGAL — DEPLOY DIBLOKIR"
        explanation = "Kode tidak dikirim ke FTP; production lama tetap berjalan."
    elif failed_deploy:
        headline = "⚠️ DEPLOY FTP GAGAL"
        explanation = "Verifikasi/source selesai, tetapi upload hosting gagal. Jangan buat commit retry."
    elif conclusion == "success":
        if verify_jobs:
            headline = "✅ VERIFIKASI + DEPLOY BERHASIL"
        else:
            headline = "✅ DEPLOY BERHASIL"
        explanation = "Workflow main selesai tanpa error."
    elif conclusion == "cancelled":
        headline = "⚪ WORKFLOW DEPLOY DIBATALKAN"
        explanation = "Tidak ada klaim bahwa production sudah diperbarui."
    else:
        headline = "❌ WORKFLOW DEPLOY GAGAL"
        explanation = "Perlu melihat step GitHub yang gagal sebelum tindakan berikutnya."

    run_id = int(run.get("id") or 0)
    sha = str(run.get("head_sha") or "")[:7] or "unknown"
    url = str(run.get("html_url") or f"https://github.com/{REPO}/actions/runs/{run_id}")
    return (
        f"{headline}\n"
        f"Commit: {sha}\n"
        f"Run: {run_id}\n"
        f"{explanation}"
        f"{concise_error(details)}\n"
        f"Detail: {url}"
    )


def baseline(runs: list[dict[str, Any]], state: dict[str, Any]) -> int:
    latest_id = max((int(run.get("id") or 0) for run in runs), default=0)
    state.update(
        {
            "initialized": True,
            "last_processed_run_id": latest_id,
            "error_count": 0,
            "error_alerted": False,
        }
    )
    write_state(state)
    return latest_id


def handle_api_error(state: dict[str, Any], error: Exception) -> str:
    count = int(state.get("error_count") or 0) + 1
    state["error_count"] = count
    should_alert = count == 3 or (count > 3 and count % 12 == 0)
    if should_alert:
        state["error_alerted"] = True
    write_state(state)
    if not should_alert:
        return ""
    reason = " ".join(str(error).split())[:240]
    return (
        "⚠️ WATCHDOG GITHUB BERMASALAH\n"
        f"Gagal membaca GitHub API {count} kali berturut-turut.\n"
        f"Error: {reason}\n"
        "Ini masalah pemantauan; bukan bukti deploy gagal."
    )


def run_watchdog() -> str:
    state = load_state()
    try:
        runs = workflow_runs()
    except Exception as error:
        return handle_api_error(state, error)

    was_alerted = bool(state.get("error_alerted"))
    state["error_count"] = 0
    state["error_alerted"] = False

    if not state.get("initialized"):
        baseline(runs, state)
        return ""

    last_id = int(state.get("last_processed_run_id") or 0)
    completed = sorted(
        (
            run
            for run in runs
            if int(run.get("id") or 0) > last_id
            and run.get("status") == "completed"
        ),
        key=lambda run: int(run.get("id") or 0),
    )

    messages: list[str] = []
    if was_alerted:
        messages.append("✅ WATCHDOG GITHUB PULIH\nGitHub API kembali dapat dibaca.")

    highest_processed = last_id
    for run in completed:
        run_id = int(run.get("id") or 0)
        try:
            jobs = jobs_for(run_id)
        except Exception:
            jobs = []
        annotations: dict[int, list[str]] = {}
        for job in jobs:
            if job.get("conclusion") in ("success", "skipped", None):
                continue
            job_id = int(job.get("id") or 0)
            if job_id:
                annotations[job_id] = failure_annotations(job_id)
        messages.append(classify_run(run, jobs, annotations))
        highest_processed = max(highest_processed, run_id)

    state["last_processed_run_id"] = highest_processed
    write_state(state)
    return "\n\n".join(messages)


def self_test() -> None:
    base_run = {
        "id": 100,
        "head_sha": "abcdef012345",
        "html_url": "https://example.test/run/100",
    }

    success = classify_run(
        {**base_run, "conclusion": "success"},
        [
            {"id": 1, "name": "Verify", "conclusion": "success"},
            {"id": 2, "name": "Deploy via FTP", "conclusion": "success"},
        ],
    )
    assert success.startswith("✅ VERIFIKASI + DEPLOY BERHASIL")

    blocked = classify_run(
        {**base_run, "conclusion": "failure"},
        [
            {"id": 3, "name": "Verification", "conclusion": "failure"},
            {"id": 4, "name": "Deploy via FTP", "conclusion": "skipped"},
        ],
        {3: ["test failed"]},
    )
    assert blocked.startswith("❌ VERIFIKASI GAGAL — DEPLOY DIBLOKIR")
    assert "test failed" in blocked

    ftp_failed = classify_run(
        {**base_run, "conclusion": "failure"},
        [{"id": 5, "name": "Deploy via FTP", "conclusion": "failure"}],
        {5: ["Error: Timeout (control socket)"]},
    )
    assert ftp_failed.startswith("⚠️ DEPLOY FTP GAGAL")
    assert "Timeout (control socket)" in ftp_failed

    print("PASS: watchdog classification self-test")


def main(argv: list[str]) -> int:
    if len(argv) > 1 and argv[1] == "--self-test":
        self_test()
        return 0
    if len(argv) > 1 and argv[1] == "--baseline":
        try:
            runs = workflow_runs()
            latest_id = baseline(runs, load_state())
        except Exception as error:
            print(f"FAIL: baseline GitHub API — {error}", file=sys.stderr)
            return 1
        print(f"BASELINE: run {latest_id}; run lama tidak akan dikirim ulang")
        return 0
    if len(argv) > 1:
        print("Usage: github_deploy_watchdog.py [--self-test|--baseline]", file=sys.stderr)
        return 2

    output = run_watchdog()
    if output:
        print(output)
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
