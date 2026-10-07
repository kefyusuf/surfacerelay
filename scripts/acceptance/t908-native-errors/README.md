# T-908 native rejection fixture

Standalone diagnostic fixture, independent of SurfaceRelay and application writes.
It is a retained regression asset, not a generated consumer demo or production API.

Serve this directory on loopback port 4187. For example, use the existing local
verification image without building or modifying it:

```powershell
& 'C:\Users\yukonit\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe' run --rm --name surfacerelay-t908-characterization -d -p 127.0.0.1:4187:4187 --mount 'type=bind,source=E:\projects\surfacerelay\scripts\acceptance\t908-native-errors,target=/fixture,readonly' -w /fixture surfacerelay-t807a-live:local python3 -m http.server 4187 --bind 0.0.0.0
```

Open `http://127.0.0.1:4187/` in the configured isolated Chrome native WebMCP
connection. Discover tools with `list_webmcp_tools` and invoke each `t908_*` tool
with `{}` using `execute_webmcp_tool`. Use native execution, not JavaScript
evaluation or a mocked model context. Record the installed browser/client
versions and exact `status`, `errorText` and `output` for each call.

Twelve tools compare eight rejected values with one resolved synthetic server
result and three Phase 1 controls: undefined, null and envelope-shaped business
output. Error messages are fixed harmless strings. No credentials, personal
browser profile, application endpoint or external provider is involved.

The fixture does not test actual user cancellation, driver dispatch frontiers,
authority, or application effects. Keep those separate from message visibility.

Close the task-created tab and stop only the container started by this recipe:

```powershell
& 'C:\Users\yukonit\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe' stop surfacerelay-t908-characterization
```

`--rm` removes the container after stopping. No image or volume is created.
Do not stop an identically named container unless this task started it.
