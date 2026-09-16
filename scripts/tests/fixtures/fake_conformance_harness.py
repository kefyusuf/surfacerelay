#!/usr/bin/env python3
import json
import sys
import time

mode = sys.argv[1]
request = json.loads(sys.stdin.read())

if mode == "hang":
    time.sleep(5)
    raise SystemExit(0)
if mode == "nonzero":
    raise SystemExit(7)
if mode == "invalid-json":
    print("not-json")
    raise SystemExit(0)
if mode == "empty-stdout":
    raise SystemExit(0)
if mode == "extra-stdout":
    print("diagnostic that must not be on stdout")

response = {
    "protocolVersion": "0.1",
    "requestId": request["requestId"],
    "scenarioId": request["scenarioId"],
    "targetId": request["targetId"],
    "profile": request["profile"],
    "observation": {
        "termination": "returned",
        "frameworkDispatchCount": 1,
        "replacementDispatchCount": 0,
    },
}

if mode == "wrong-request":
    response["requestId"] = "wrong"
elif mode == "wrong-scenario":
    response["scenarioId"] = "WRONG"
elif mode == "wrong-version":
    response["protocolVersion"] = "9.9"
elif mode == "wrong-target":
    response["targetId"] = "browser/wrong"
elif mode == "wrong-profile":
    response["profile"] = "runtime-binding/wrong"
elif mode == "invalid-observation":
    response["observation"] = {"termination": "returned", "passed": True}

print(json.dumps(response, separators=(",", ":")))
