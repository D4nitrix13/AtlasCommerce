/**
 * RNF CRUD endpoint tests — POST/PUT/DELETE.
 *
 * Mirror of `rf.test.ts` for the RNF item type. Covers REQ-20 +
 * REQ-21 + REQ-22 for RNF.
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

vi.mock("../../../../../src/lib/rem/loader", () => ({
  loadAllRequirements: vi.fn(),
  clearLoaderCache: vi.fn(),
}));

import * as fsPromises from "node:fs/promises";
import { clearLoaderCache } from "../../../../../src/lib/rem/loader";

let WORK_DIR = mkdtempSync(join(tmpdir(), "rem-rnf-test-"));

beforeEach(() => {
  rmSync(WORK_DIR, { recursive: true, force: true });
  WORK_DIR = mkdtempSync(join(tmpdir(), "rem-rnf-test-"));
  mkdirSync(join(WORK_DIR, "_p"), { recursive: true });
  mkdirSync(join(WORK_DIR, "scripts/requirements/req"), { recursive: true });
});

afterEach(() => {
  vi.restoreAllMocks();
});

function loadModule(): Promise<typeof import("../../../../../src/pages/api/rem/rnf/index")> {
  return import("../../../../../src/pages/api/rem/rnf/index");
}
function loadUidModule(): Promise<typeof import("../../../../../src/pages/api/rem/rnf/[uid]")> {
  return import("../../../../../src/pages/api/rem/rnf/[uid]");
}

async function callEndpoint(
  handler: "POST" | "PUT" | "DELETE",
  request: Request,
  params: Record<string, string> = {},
): Promise<Response> {
  const mod = handler === "POST" ? await loadModule() : await loadUidModule();
  const fn = mod[handler];
  if (fn === undefined) throw new Error(`Handler ${handler} missing`);
  return (fn as unknown as (ctx: unknown) => Response | Promise<Response>)({
    params,
    request,
    url: new URL(request.url),
    locals: undefined,
  });
}

function jsonRequest(method: string, body: unknown, path = "/api/rem/rnf"): Request {
  return new Request(`http://localhost${path}`, {
    method,
    headers: { "content-type": "application/json" },
    body: JSON.stringify(body),
  });
}

const validBody = {
  code: "RNF-SEC-099",
  title: "RNF Title",
  rationale: "r",
  priority: "M" as const,
  status: "pending" as const,
  category: "Security",
  fuentes: [] as string[],
  dependencias: [] as string[],
};

function withReqDir<T>(fn: () => Promise<T> | T): Promise<T> | T {
  const origCwd = process.cwd();
  process.chdir(join(WORK_DIR, "_p"));
  const result = fn();
  if (result instanceof Promise) {
    return result.finally(() => process.chdir(origCwd)) as Promise<T>;
  }
  process.chdir(origCwd);
  return result;
}

describe("POST /api/rem/rnf (REQ-20)", () => {
  it("REQ-20#1: creates an RNF YAML and returns 201 + uid", async () => {
    await withReqDir(async () => {
      const response = await callEndpoint("POST", jsonRequest("POST", validBody));
      expect(response.status).toBe(201);
      const body = await response.json();
      expect(body.ok).toBe(true);
      expect(body.uid).toMatch(/^rnf_\d{3}$/);
      const files = readdirSync(join(WORK_DIR, "scripts/requirements/req")).filter(
        (n) => n.endsWith(".yml")
      );
      expect(files.length).toBe(1);
      const content = readFileSync(
        join(WORK_DIR, "scripts/requirements/req", files[0]!),
        "utf-8"
      );
      expect(content).toMatch(/code: "RNF-SEC-099"/);
      expect(content).toMatch(/category: "Security"/);
    });
  });

  it("REQ-20#2: invalid code regex returns 400", async () => {
    await withReqDir(async () => {
      const response = await callEndpoint(
        "POST",
        jsonRequest("POST", { ...validBody, code: "INVALID" })
      );
      expect(response.status).toBe(400);
      const body = await response.json();
      expect(body.error).toMatch(/^crud:validation:/);
    });
  });

  it("REQ-20#3: write error returns 500 + sibling files untouched (NFR-10)", async () => {
    await withReqDir(async () => {
      const reqDir = join(WORK_DIR, "scripts/requirements/req");
      await fsPromises.writeFile(join(reqDir, "rf_001_x.yml"), "rf_001:\n");
      const fsModule = await import("node:fs");
      fsModule.chmodSync(reqDir, 0o555);
      let response: Response;
      try {
        response = await callEndpoint("POST", jsonRequest("POST", validBody));
      } finally {
        fsModule.chmodSync(reqDir, 0o755);
      }
      expect(response.status).toBe(500);
      expect(readdirSync(reqDir)).toContain("rf_001_x.yml");
    });
  });

  it("REQ-20#4: success calls clearLoaderCache()", async () => {
    await withReqDir(async () => {
      await callEndpoint("POST", jsonRequest("POST", validBody));
      expect(clearLoaderCache).toHaveBeenCalled();
    });
  });
});

describe("PUT /api/rem/rnf/[uid] (REQ-21)", () => {
  it("REQ-21#1: PUT on existing UID overwrites the file and returns 200", async () => {
    await withReqDir(async () => {
      const reqDir = join(WORK_DIR, "scripts/requirements/req");
      const targetPath = join(reqDir, "rnf_001_RNF-SEC-001.yml");
      await fsPromises.writeFile(
        targetPath,
        'rnf_001:\n  code: "RNF-SEC-001"\n  title: "Old"\n  rationale: ""\n  priority: M\n  status: pending\n  category: "Security"\n  fuentes: []\n  dependencias: []\n  estabilidad: PD\n  version: "1.0"\n  autores: []\n'
      );
      const response = await callEndpoint(
        "PUT",
        jsonRequest("PUT", { uid: "rnf_001", title: "Updated" }, "/api/rem/rnf/rnf_001"),
        { uid: "rnf_001" }
      );
      expect(response.status).toBe(200);
      const body = await response.json();
      expect(body.uid).toBe("rnf_001");
      const updated = readFileSync(targetPath, "utf-8");
      expect(updated).toMatch(/title: "Updated"/);
    });
  });

  it("REQ-21#2: PUT on unknown UID returns 404", async () => {
    await withReqDir(async () => {
      const response = await callEndpoint(
        "PUT",
        jsonRequest("PUT", { uid: "rnf_999", code: "RNF-SEC-099", title: "x" }),
        { uid: "rnf_999" }
      );
      expect(response.status).toBe(404);
      const body = await response.json();
      expect(body.error).toBe("crud:notfound:rnf_999");
    });
  });

  it("REQ-21#3: invalid body returns 400", async () => {
    await withReqDir(async () => {
      const reqDir = join(WORK_DIR, "scripts/requirements/req");
      await fsPromises.writeFile(
        join(reqDir, "rnf_001_RNF-SEC.yml"),
        'rnf_001:\n  code: "RNF-SEC-001"\n'
      );
      const response = await callEndpoint(
        "PUT",
        jsonRequest("PUT", { uid: "rnf_001", code: "WRONG" }),
        { uid: "rnf_001" }
      );
      expect(response.status).toBe(400);
      const body = await response.json();
      expect(body.error).toMatch(/^crud:validation:/);
    });
  });

  it("REQ-21#4: empty payload returns 400 (code defaults to invalid)", async () => {
    await withReqDir(async () => {
      const reqDir = join(WORK_DIR, "scripts/requirements/req");
      await fsPromises.writeFile(
        join(reqDir, "rnf_001_RNF-SEC.yml"),
        'rnf_001:\n  code: "RNF-SEC-001"\n'
      );
      const response = await callEndpoint(
        "PUT",
        jsonRequest("PUT", { uid: "rnf_001" }, "/api/rem/rnf/rnf_001"),
        { uid: "rnf_001" }
      );
      expect(response.status).toBe(400);
    });
  });
});

describe("DELETE /api/rem/rnf/[uid] (REQ-22)", () => {
  it("REQ-22#1: DELETE on existing UID returns 204 with empty body", async () => {
    await withReqDir(async () => {
      const reqDir = join(WORK_DIR, "scripts/requirements/req");
      await fsPromises.writeFile(join(reqDir, "rnf_001_RNF-SEC.yml"), "rnf_001:\n");
      const response = await callEndpoint(
        "DELETE",
        new Request("http://localhost/api/rem/rnf/rnf_001", { method: "DELETE" }),
        { uid: "rnf_001" }
      );
      expect(response.status).toBe(204);
      const text = await response.text();
      expect(text).toBe("");
      expect(readdirSync(reqDir).some((n) => n.startsWith("rnf_001"))).toBe(false);
    });
  });

  it("REQ-22#2: DELETE on unknown UID returns 404", async () => {
    await withReqDir(async () => {
      const response = await callEndpoint(
        "DELETE",
        new Request("http://localhost/api/rem/rnf/rnf_999", { method: "DELETE" }),
        { uid: "rnf_999" }
      );
      expect(response.status).toBe(404);
      const body = await response.json();
      expect(body.error).toBe("crud:notfound:rnf_999");
    });
  });

  it("REQ-22#3: DELETE unlink throw returns 500 + file remains", async () => {
    await withReqDir(async () => {
      const reqDir = join(WORK_DIR, "scripts/requirements/req");
      await fsPromises.writeFile(join(reqDir, "rnf_001_RNF-SEC.yml"), "rnf_001:\n");
      const fsModule = await import("node:fs");
      fsModule.chmodSync(reqDir, 0o555);
      let response: Response;
      try {
        response = await callEndpoint(
          "DELETE",
          new Request("http://localhost/api/rem/rnf/rnf_001", { method: "DELETE" }),
          { uid: "rnf_001" }
        );
      } finally {
        fsModule.chmodSync(reqDir, 0o755);
      }
      expect(response.status).toBe(500);
      expect(readdirSync(reqDir).some((n) => n.startsWith("rnf_001"))).toBe(true);
    });
  });

  it("REQ-22#4: DELETE clears loader cache on success", async () => {
    await withReqDir(async () => {
      const reqDir = join(WORK_DIR, "scripts/requirements/req");
      await fsPromises.writeFile(join(reqDir, "rnf_001_RNF-SEC.yml"), "rnf_001:\n");
      const callsBefore = vi.mocked(clearLoaderCache).mock.calls.length;
      const response = await callEndpoint(
        "DELETE",
        new Request("http://localhost/api/rem/rnf/rnf_001", { method: "DELETE" }),
        { uid: "rnf_001" }
      );
      expect(response.status).toBe(204);
      expect(vi.mocked(clearLoaderCache).mock.calls.length).toBeGreaterThan(callsBefore);
    });
  });
});
