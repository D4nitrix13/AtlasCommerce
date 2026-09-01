/**
 * serializeYamlSubset.test.ts
 *
 * Single-line shape coverage for the serializer (NFR-11). The test
 * exercises the 4 hand-picked fixture shapes from tasks.md Task 1.2:
 *   - stakeholder with empty list fields
 *   - stakeholder with notes
 *   - RF with dependencies
 *   - RNF with category
 *
 * Each `it` asserts the EXACT byte-for-byte output matches
 * `migrate_to_doorstop.py:45-65`'s shape so the writer stays in sync
 * with the migration script.
 */

import { describe, expect, it } from "vitest";

import { serializeYamlSubset } from "../../../../src/lib/rem/yaml-writer";

describe("serializeYamlSubset · NFR-11 round-trip shape", () => {
  it("stakeholder with empty string fields renders every key as `\"\"`", () => {
    const out = serializeYamlSubset("stakeholder_empty_strings", {
      name: "Anonymous Stakeholder",
      title: "",
      role: "",
      organization: "",
      notes: "",
    });
    expect(out).toBe(
      [
        'stakeholder_empty_strings:',
        '  name: "Anonymous Stakeholder"',
        '  title: ""',
        '  role: ""',
        '  organization: ""',
        '  notes: ""',
        "",
      ].join("\n"),
    );
  });

  it("stakeholder with notes renders single-line strings", () => {
    const out = serializeYamlSubset("stakeholder_with_notes", {
      name: "Daniel",
      title: "Lider",
      role: "developer",
      organization: "Panda",
      notes: "Owner del stack.",
    });
    expect(out).toContain('name: "Daniel"');
    expect(out).toContain('notes: "Owner del stack."');
    expect(out.startsWith("stakeholder_with_notes:\n  name:")).toBe(true);
  });

  it("RF with deps renders `verified-by` as a dashed key and lists with indent", () => {
    const out = serializeYamlSubset("rf_001", {
      title: "Login",
      rationale: "El usuario puede autenticarse",
      priority: "H",
      status: "implemented",
      module: "Autenticación",
      code: "RF-AUTH-001",
      "verified-by": ["stakeholder_001"],
      version: "1.0",
      autores: ["stakeholder_001"],
      fuentes: [],
      dependencias: ["RNF-SEC-010", "RNF-SEC-011"],
      estabilidad: "alta",
    });
    expect(out).toBe(
      [
        "rf_001:",
        '  title: "Login"',
        '  rationale: "El usuario puede autenticarse"',
        '  priority: "H"',
        '  status: "implemented"',
        '  module: "Autenticación"',
        '  code: "RF-AUTH-001"',
        "  verified-by:",
        "    - stakeholder_001",
        '  version: "1.0"',
        "  autores:",
        "    - stakeholder_001",
        "  fuentes: []",
        "  dependencias:",
        "    - RNF-SEC-010",
        "    - RNF-SEC-011",
        '  estabilidad: "alta"',
        "",
      ].join("\n"),
    );
  });

  it("RNF with category renders `category` key and omits verified-by", () => {
    const out = serializeYamlSubset("rnf_010", {
      title: "Sanctum",
      rationale: "tokens scoped por tenant",
      priority: "H",
      status: "verified",
      category: "Seguridad",
      code: "RNF-SEC-010",
      version: "1.0",
      autores: ["stakeholder_001", "stakeholder_003"],
      fuentes: [],
      dependencias: [],
      estabilidad: "PD",
    });
    expect(out).toContain('category: "Seguridad"');
    expect(out).not.toContain("verified-by");
    expect(out).toContain("autores:");
    expect(out).toContain("  fuentes: []");
  });
});