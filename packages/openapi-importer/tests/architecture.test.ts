import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { extname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import { describe, expect, it } from 'vitest';

const packageRoot = fileURLToPath(new URL('../', import.meta.url));
const srcRoot = join(packageRoot, 'src');
const manifest = JSON.parse(readFileSync(join(packageRoot, 'package.json'), 'utf8')) as {
  dependencies?: Record<string, string>;
  devDependencies?: Record<string, string>;
};

function listTypeScriptFiles(root: string): string[] {
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

      if (entry.isFile() && extname(entry.name) === '.ts') {
        files.push(path);
      }
    }
  }

  return files.sort();
}

function moduleSpecifiers(source: string): string[] {
  const patterns = [
    /(?:import|export)\s+(?:type\s+)?(?:[^'";]+?\s+from\s+)?['"]([^'"]+)['"]/g,
    /import\s*\(\s*['"]([^'"]+)['"]\s*\)/g,
  ];

  return patterns.flatMap((pattern) =>
    Array.from(source.matchAll(pattern), (match) => match[1]).filter(
      (value): value is string => value !== undefined,
    ),
  );
}

describe('OpenAPI importer architecture boundary', () => {
  it('keeps yaml as the only production dependency', () => {
    expect(manifest.dependencies).toEqual({ yaml: '^2.9.1' });

    const forbiddenDependency = Object.keys({
      ...manifest.dependencies,
      ...manifest.devDependencies,
    }).find((name) =>
      /(?:surfacerelay|laravel|mcp|webmcp|swagger-parser|openapi-parser)/i.test(name),
    );

    expect(forbiddenDependency).toBeUndefined();
  });

  it('avoids whole-remainder allocation for every JSON numeric token', () => {
    const parser = readFileSync(
      join(srcRoot, 'source', 'parse-json.ts'),
      'utf8',
    );

    expect(parser).not.toMatch(/source\.slice\(this\.offset\)/);
  });

  it('requires the minimal production entry point', () => {
    expect(existsSync(join(srcRoot, 'index.ts'))).toBe(true);
  });

  it('allowlists production module capabilities', () => {
    const allowedBareSpecifiers = new Set(['yaml', 'node:buffer']);

    for (const path of listTypeScriptFiles(srcRoot)) {
      const source = readFileSync(path, 'utf8');

      for (const specifier of moduleSpecifiers(source)) {
        if (specifier.startsWith('.')) {
          continue;
        }

        expect(
          allowedBareSpecifiers.has(specifier),
          `Unexpected production module capability in ${path}: ${specifier}`,
        ).toBe(true);
      }

      expect(source, `Production source must not call fetch(): ${path}`).not.toMatch(
        /\bfetch\s*\(/,
      );
    }
  });
});
