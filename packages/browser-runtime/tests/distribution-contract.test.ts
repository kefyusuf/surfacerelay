import { execFileSync } from 'node:child_process';
import {
  existsSync,
  mkdtempSync,
  readFileSync,
  readdirSync,
  rmSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { extname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

import { describe, expect, it } from 'vitest';

const packageRoot = fileURLToPath(new URL('../', import.meta.url));
const manifestPath = join(packageRoot, 'package.json');
const buildConfigPath = join(packageRoot, 'tsconfig.build.json');

interface PackageManifest {
  name?: string;
  version?: string;
  private?: boolean;
  type?: string;
  types?: string;
  main?: string;
  files?: string[];
  exports?: Record<string, unknown>;
  scripts?: Record<string, string>;
}

interface BuildConfig {
  extends?: string;
  compilerOptions?: Record<string, unknown>;
  include?: string[];
}

function readJson<T>(path: string): T {
  return JSON.parse(readFileSync(path, 'utf8')) as T;
}

function listFiles(root: string): string[] {
  if (!existsSync(root)) {
    return [];
  }

  const files: string[] = [];
  const pending = [root];

  while (pending.length > 0) {
    const current = pending.pop();
    if (current === undefined) {
      break;
    }

    for (const entry of readdirSync(current, { withFileTypes: true })) {
      const path = join(current, entry.name);

      if (entry.isDirectory()) {
        pending.push(path);
        continue;
      }

      if (entry.isFile()) {
        files.push(relative(root, path).replaceAll('\\', '/'));
      }
    }
  }

  return files.sort();
}

describe('browser-runtime distribution contract', () => {
  it('preserves the source-only version and publication blocker', () => {
    const manifest = readJson<PackageManifest>(manifestPath);

    expect(manifest.name).toBe('@surfacerelay/browser-runtime');
    expect(manifest.version).toBe('0.0.0-dev');
    expect(manifest.private).toBe(true);
    expect(manifest.type).toBe('module');
  });

  it('declares only the reviewed root ESM entry, declarations, files, and build script', () => {
    const manifest = readJson<PackageManifest>(manifestPath);

    expect(manifest.types).toBe('./dist/index.d.ts');
    expect(manifest.main).toBeUndefined();
    expect(manifest.files).toEqual(['dist', 'README.md', 'LICENSE']);
    expect(manifest.exports).toEqual({
      '.': {
        types: './dist/index.d.ts',
        import: './dist/index.js',
      },
    });
    expect(manifest.scripts?.build).toBe('tsc -p tsconfig.build.json');
  });

  it('defines a bounded declarations-only ESM build configuration', () => {
    expect(existsSync(buildConfigPath)).toBe(true);

    const config = readJson<BuildConfig>(buildConfigPath);

    expect(config.extends).toBe('./tsconfig.json');
    expect(config.compilerOptions).toEqual({
      noEmit: false,
      rootDir: 'src',
      outDir: 'dist',
      declaration: true,
      declarationMap: false,
      sourceMap: false,
    });
    expect(config.include).toEqual(['src/**/*.ts']);
  });

  it('emits JavaScript and declarations without maps or CommonJS output', () => {
    const buildRoot = mkdtempSync(join(tmpdir(), 'surfacerelay-t803-build-'));

    try {
      execFileSync(
        'npm',
        ['run', 'build', '--', '--outDir', buildRoot],
        {
          cwd: packageRoot,
          encoding: 'utf8',
          stdio: 'pipe',
        },
      );

      const files = listFiles(buildRoot);

      expect(files).toContain('index.js');
      expect(files).toContain('index.d.ts');
      expect(files.some((file) => file.endsWith('.map'))).toBe(false);
      expect(files.some((file) => extname(file) === '.ts' && !file.endsWith('.d.ts'))).toBe(false);

      const indexJavaScript = readFileSync(join(buildRoot, 'index.js'), 'utf8');

      expect(indexJavaScript).toMatch(/\bexport\s*\{/);
      expect(indexJavaScript).not.toMatch(/\brequire\s*\(/);
      expect(indexJavaScript).not.toMatch(/\bmodule\.exports\b/);
      expect(indexJavaScript).not.toMatch(/\bexports\./);
    } finally {
      rmSync(buildRoot, { recursive: true, force: true });
    }
  });
});
