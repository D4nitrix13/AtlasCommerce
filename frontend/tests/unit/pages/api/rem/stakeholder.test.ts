/**
 * Stakeholder CRUD endpoint tests — POST/PUT/DELETE.
 *
 * Covers REQ-20#1..#4 + REQ-21#1..#3 + REQ-22#1..#4 (per design
 * §6 mapping). The loader is mocked so the test runs offline; the
 * yaml-writer is the real one (vitest's jsdom env supports
 * node:fs/promises through the project's node.d.ts shim).
 *
 * Test harness pattern (mirrors `tests/unit/api/rem-uid-html.test.ts`):
 *   - `vi.mock('../../../src/lib/rem/loader')` hoists before any
 *     import, so the endpoint imports the fake loader.
 *   - Each endpoint is imported lazily via a `loadModule()` helper so
 *     the mock is in scope.
 *   - We override the write target by `process.chdir(WORK_DIR/_p)` so
 *     the endpoint's `reqDir()` (which uses `resolve(process.cwd(),
 *     '..', 'scripts/requirements/req')`) lands at WORK_DIR/scripts/
 *     requirements/req.
 */

import { mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import {
  afterEach,
  beforeEach,
  describe,
  expect,
  it,
  vi,
} from "vitest";

// Mock the loader so we control what the endpoint sees + can spy on
// `clearLoaderCache` for REQ-20#4 / REQ-22#4.
vi.mock("../../../../../src/lib/rem/loader", () => ({
  loadAllRequirements: vi.fn(),
  clearLoaderCache: vi.fn(),
}));

// `fs/promises` is imported as a namespace so `vi.spyOn` can replace
// individual exports without breaking the destructured imports the
// writer does internally.
import * as fsPromises from "node:fs/promises";
import { clearLoaderCache } from "../../../../../src/lib/rem/loader";

let WORK_DIR = mkdtempSync(join(tmpdir(), "rem-stakeholder-test-"));

beforeEach(() => {
  // Fresh tmp work dir per test so snapshots don't bleed.
  rmSync(WORK_DIR, { recursive: true, force: true });
  WORK_DIR = mkdtempSync(join(tmpdir(), "rem-stakeholder-test-"));
  // Pre-create the structure the endpoint expects.
  mkdirSync(join(WORK_DIR, "_p"), { recursive: true });
  mkdirSync(join(WORK_DIR, "scripts/requirements/req"), { recursive: true });
});

afterEach(() => {
  vi.restoreAllMocks();
});

function loadModule(): Promise<typeof import("../../../../../src/pages/api/rem/stakeholder/index")> {
  return import("../../../../../src/pages/api/rem/stakeholder/index");
}

function loadUidModule(): Promise<typeof import("../../../../../src/pages/api/rem/stakeholder/[uid]")> {
  return import("../../../../../src/pages/api/rem/stakeholder/[uid]");
}

async function callEndpoint(
  handler: "POST" | "PUT" | "DELETE",
  request: Request,
  params: Record<string, string> = {},
): Promise<Response> {
  const mod = handler === "POST" ? await loadModule() : await loadUidModule();
  const fn = mod[handler];
  if (fn === undefined) {
    throw new Error(`Handler ${handler} missing`);
  }
  return (fn as unknown as (ctx: unknown) => Response | Promise<Response>)({
    params,
    request,
    url: new URL(request.url),
    locals: undefined,
  });
}

function jsonRequest(method: string, body: unknown, path = "/api/rem/stakeholder"): Request {
  return new Request(`http://localhost${path}`, {
    method,
    headers: { "content-type": "application/json" },
    body: JSON.stringify(body),
  });
}

const sampleBody = {
  name: "Sponsor Test",
  title: "Sponsor",
  role: "owner",
  organization: "Acme",
  notes: "n",
};

/**
 * Run a callback with the endpoint's `reqDir()` pointing at WORK_DIR.
 * `reqDir()` resolves to `<cwd>/../scripts/requirements/req`, so we
 * chdir into WORK_DIR's `_p/` child folder. The endpoint then writes
 * to WORK_DIR/scripts/requirements/req/.
 */
function withReqDir<T>(fn: () => Promise<T> | T): Promise<T> | T {
  const origCwd = process.cwd();
  const child = join(WORK_DIR, "_p");
  process.chdir(child);
  const result = fn();
  if (result instanceof Promise) {
    return result.finally(() => {
      process.chdir(origCwd);
    }) as Promise<T>;
  }
  process.chdir(origCwd);
  return result;
}

describe("POST /api/rem/stakeholder (REQ-20#1..#4)", () => {
  it("REQ-20#1: creates a stakeholder YAML on disk and returns 201 + uid", async () => {
    await withReqDir(async () => {
      const response = await callEndpoint("POST", jsonRequest("POST", sampleBody));
      expect(response.status).toBe(201);
      const body = await response.json();
      expect(body.ok).toBe(true);
      expect(body.uid).toBe("stakeholder_001");
      // File exists on disk.
      const files = readdirSync(join(WORK_DIR, "scripts/requirements/req")).filter(
        (n) => n.endsWith(".yml")
      );
      expect(files.length).toBe(1);
      expect(files[0]).toMatch(/^stakeholder_001_/);
      // YAML contents use the subset shape.
      const content = readFileSync(
        join(WORK_DIR, "scripts/requirements/req", files[0]!),
        "utf-8"
      );
      expect(content).toMatch(/stakeholder_001:/);
      expect(content).toMatch(/name: "Sponsor Test"/);
    });
  });

  it("REQ-20#2: missing name returns 400 + validation code", async () => {
    await withReqDir(async () => {
      const response = await callEndpoint(
        "POST",
        jsonRequest("POST", { title: "no-name" })
      );
      expect(response.status).toBe(400);
      const body = await response.json();
      expect(body.ok).toBe(false);
      expect(body.error).toMatch(/^crud:validation:/);
    });
  });

  it("REQ-20#3: write error returns 500 (sibling files untouched — NFR-10)", async () => {
    await withReqDir(async () => {
      const reqDir = join(WORK_DIR, "scripts/requirements/req");
      // Pre-create a sibling file
      await fsPromises.writeFile(join(reqDir, "rf_001_RF-AUTH.yml"), "rf_001:\n");
      // Force writeYamlAtomic to fail at the rename stage by making
      // the target directory read-only. We can't vi.spyOn rename
      // because the ESM namespace is non-configurable; chmod 0o555
      // makes the rename of `<file>.tmp → <file>.yml` fail with
      // EACCES on POSIX. The .tmp write succeeds (different parent
      // dir check), but the rename fails.
      const fsModule = await import("node:fs");
      fsModule.chmodSync(reqDir, 0o555);
      let response: Response;
      try {
        response = await callEndpoint("POST", jsonRequest("POST", sampleBody));
      } finally {
        fsModule.chmodSync(reqDir, 0o755);
      }
      expect(response.status).toBe(500);
      const body = await response.json();
      expect(body.error).toMatch(/^crud:write:/);
      // Sibling still on disk.
      expect(readdirSync(reqDir)).toContain("rf_001_RF-AUTH.yml");
    });
  });

  it("REQ-20#4: success calls clearLoaderCache()", async () => {
    await withReqDir(async () => {
      await callEndpoint("POST", jsonRequest("POST", sampleBody));
      expect(clearLoaderCache).toHaveBeenCalled();
    });
  });
});

describe("PUT /api/rem/stakeholder/[uid] (REQ-21)", () => {
  it("REQ-21#1: PUT on existing UID overwrites the file and returns 200", async () => {
    await withReqDir(async () => {
      const reqDir = join(WORK_DIR, "scripts/requirements/req");
      const targetPath = join(reqDir, "stakeholder_001_sponsor.yml");
      await fsPromises.writeFile(
        targetPath,
        'stakeholder_001:\n  name: "Old"\n  title: ""\n  role: ""\n  organization: ""\n  notes: ""\n'
      );
      const response = await callEndpoint(
        "PUT",
        jsonRequest(
          "PUT",
          { uid: "stakeholder_001", name: "Updated", title: "New" },
          "/api/rem/stakeholder/stakeholder_001"
        ),
        { uid: "stakeholder_001" }
      );
      expect(response.status).toBe(200);
      const body = await response.json();
      expect(body.ok).toBe(true);
      expect(body.uid).toBe("stakeholder_001");
      const updated = readFileSync(targetPath, "utf-8");
      expect(updated).toMatch(/name: "Updated"/);
      expect(updated).toMatch(/title: "New"/);
    });
  });

  it("REQ-21#2: PUT on unknown UID returns 404 with crud:notfound code", async () => {
    await withReqDir(async () => {
      const response = await callEndpoint(
        "PUT",
        jsonRequest("PUT", { uid: "stakeholder_999", name: "x" }),
        { uid: "stakeholder_999" }
      );
      expect(response.status).toBe(404);
      const body = await response.json();
      expect(body.error).toBe("crud:notfound:stakeholder_999");
    });
  });

  it("REQ-21#3: invalid body returns 400 + validation code", async () => {
    await withReqDir(async () => {
      const reqDir = join(WORK_DIR, "scripts/requirements/req");
      const targetPath = join(reqDir, "stakeholder_001_sponsor.yml");
      await fsPromises.writeFile(
        targetPath,
        'stakeholder_001:\n  name: ""\n  title: ""\n  role: ""\n  organization: ""\n  notes: ""\n'
      );
      // Body missing name entirely — defaulted body has empty name
      // which fails Zod name min(1).
      const response = await callEndpoint(
        "PUT",
        jsonRequest("PUT", { uid: "stakeholder_001", title: "only" }),
        { uid: "stakeholder_001" }
      );
      expect(response.status).toBe(400);
      const body = await response.json();
      expect(body.error).toMatch(/^crud:validation:/);
    });
  });
});

describe("DELETE /api/rem/stakeholder/[uid] (REQ-22)", () => {
  it("REQ-22#1: DELETE on existing UID returns 204 with empty body", async () => {
    await withReqDir(async () => {
      const reqDir = join(WORK_DIR, "scripts/requirements/req");
      const targetPath = join(reqDir, "stakeholder_001_sponsor.yml");
      await fsPromises.writeFile(targetPath, "stakeholder_001:\n");
      const response = await callEndpoint(
        "DELETE",
        new Request("http://localhost/api/rem/stakeholder/stakeholder_001", {
          method: "DELETE",
        }),
        { uid: "stakeholder_001" }
      );
      expect(response.status).toBe(204);
      const text = await response.text();
      expect(text).toBe("");
      expect(
        readdirSync(reqDir).some((n) => n.startsWith("stakeholder_001"))
      ).toBe(false);
    });
  });

  it("REQ-22#2: DELETE on unknown UID returns 404", async () => {
    await withReqDir(async () => {
      const response = await callEndpoint(
        "DELETE",
        new Request("http://localhost/api/rem/stakeholder/stakeholder_999", {
          method: "DELETE",
        }),
        { uid: "stakeholder_999" }
      );
      expect(response.status).toBe(404);
      const body = await response.json();
      expect(body.error).toBe("crud:notfound:stakeholder_999");
    });
  });

  it("REQ-22#3: DELETE unlink throw returns 500 and file remains on disk", async () => {
    await withReqDir(async () => {
      const reqDir = join(WORK_DIR, "scripts/requirements/req");
      const targetPath = join(reqDir, "stakeholder_001_sponsor.yml");
      await fsPromises.writeFile(targetPath, "stakeholder_001:\n");
      // We exercise the "delete fails" branch by removing the parent
      // directory's write permissions, so unlinkSync throws EACCES.
      // (We can't vi.spyOn unlinkSync because the ESM namespace is
      // non-configurable, and we don't want to mock the whole
      // `node:fs` module for one test.) Setting the file immutable
      // would also work on Linux but is more invasive.
      //
      // Removing write permission is enough: unlink on a read-only
      // file in a read-only dir fails with EACCES on POSIX. Use
      // chmodSync to flip the parent directory to 0o555.
      const fsModule = await import("node:fs");
      fsModule.chmodSync(reqDir, 0o555);
      let response: Response;
      try {
        response = await callEndpoint(
          "DELETE",
          new Request("http://localhost/api/rem/stakeholder/stakeholder_001", {
            method: "DELETE",
          }),
          { uid: "stakeholder_001" }
        );
      } finally {
        // Restore so afterEach cleanup works.
        fsModule.chmodSync(reqDir, 0o755);
      }
      expect(response.status).toBe(500);
      // File is still on disk.
      expect(
        readdirSync(reqDir).some((n) => n.startsWith("stakeholder_001"))
      ).toBe(true);
    });
  });

  it("REQ-22#4: DELETE clears loader cache on success", async () => {
    await withReqDir(async () => {
      const reqDir = join(WORK_DIR, "scripts/requirements/req");
      const targetPath = join(reqDir, "stakeholder_001_sponsor.yml");
      await fsPromises.writeFile(targetPath, "stakeholder_001:\n");
      const callsBefore = vi.mocked(clearLoaderCache).mock.calls.length;
      const response = await callEndpoint(
        "DELETE",
        new Request("http://localhost/api/rem/stakeholder/stakeholder_001", {
          method: "DELETE",
        }),
        { uid: "stakeholder_001" }
      );
      expect(response.status).toBe(204);
      expect(vi.mocked(clearLoaderCache).mock.calls.length).toBeGreaterThan(
        callsBefore
      );
    });
  });
});
