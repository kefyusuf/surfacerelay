interface MinimalNodeProcess {
  stdin: {
    setEncoding(encoding: 'utf8'): void;
    on(event: 'data', listener: (chunk: string) => void): void;
    on(event: 'end', listener: () => void): void;
  };
  stdout: { write(chunk: string): unknown };
  stderr: { write(chunk: string): unknown };
  exitCode?: number;
}

const nodeProcess = (globalThis as unknown as { process: MinimalNodeProcess }).process;

export function readSingleStdinDocument(): Promise<string> {
  return new Promise((resolve) => {
    let input = '';
    nodeProcess.stdin.setEncoding('utf8');
    nodeProcess.stdin.on('data', (chunk) => {
      input += chunk;
    });
    nodeProcess.stdin.on('end', () => {
      resolve(input);
    });
  });
}

export function writeProtocolJson(value: unknown): void {
  nodeProcess.stdout.write(`${JSON.stringify(value)}\n`);
}

export function writeDiagnostic(message: string): void {
  nodeProcess.stderr.write(message.endsWith('\n') ? message : `${message}\n`);
}

export function setFailureExitCode(): void {
  nodeProcess.exitCode = 1;
}
