/**
 * REM viewer — tree builder.
 *
 * Converts a flat `RemDataset` into the hierarchical structure the
 * sidebar renders:
 *   - Stakeholders: flat list (one node each)
 *   - Functional Requirements: grouped by `module`
 *   - Non-Functional Requirements: grouped by `category`
 *
 * Every group and stakeholder list is sorted by `code` ascending.
 * The function is pure (no I/O, no mutation) so it's trivially
 * deterministic — NFR-06 (same dataset → byte-identical tree).
 *
 * Why a separate module: the loader produces a flat list because
 * that's how Doorstop stores the data. The sidebar needs a tree.
 * Splitting the transformation into its own function lets us test
 * the grouping logic in isolation and reuse it for the full-report
 * PDF TOC in PR2.
 */

import type {
  Requirement,
  RemDataset,
  RemTree,
  Stakeholder,
  TreeNode,
} from "./types";

/**
 * Group items by a string key, sort each group by the comparator,
 * and return a stable list of `TreeNode` groups.
 */
function groupRequirements(
  items: Requirement[],
  keyOf: (item: Requirement) => string | undefined,
  dataKey: "rf" | "rnf",
): TreeNode[] {
  const buckets = new Map<string, Requirement[]>();
  for (const item of items) {
    const key = keyOf(item);
    // Items missing the structural discriminator (RF without module,
    // RNF without category) go into a fallback "Sin clasificar" group
    // so the sidebar still shows them — the UI labels the group as PD.
    const bucketKey = key ?? "__unclassified__";
    const arr = buckets.get(bucketKey);
    if (arr) {
      arr.push(item);
    } else {
      buckets.set(bucketKey, [item]);
    }
  }

  const groups: TreeNode[] = [];
  const sortedKeys = Array.from(buckets.keys()).sort((a, b) => {
    if (a === "__unclassified__") {
      return 1;
    }
    if (b === "__unclassified__") {
      return -1;
    }
    return a.localeCompare(b, "es");
  });

  for (const groupKey of sortedKeys) {
    const members = (buckets.get(groupKey) ?? []).slice().sort((a, b) =>
      a.code.localeCompare(b.code, "es"),
    );
    const label = groupKey === "__unclassified__" ? "Sin clasificar" : groupKey;
    groups.push({
      id: `${dataKey}-group-${groupKey}`,
      label,
      children: members.map<TreeNode>((m) => ({
        id: m.code,
        label: m.code,
        data: m,
      })),
    });
  }
  return groups;
}

/** Build the stakeholder branch: flat list, sorted by UID. */
function buildStakeholderBranch(stakeholders: Stakeholder[]): TreeNode[] {
  return stakeholders
    .slice()
    .sort((a, b) => a.uid.localeCompare(b.uid, "es"))
    .map<TreeNode>((s) => ({
      id: s.uid,
      label: s.name || s.uid,
      data: s,
    }));
}

/**
 * Public API: turn a `RemDataset` into the three-branch tree the
 * sidebar renders. Pure function — NFR-06.
 */
export function buildTree(dataset: RemDataset): RemTree {
  return {
    stakeholders: buildStakeholderBranch(dataset.stakeholders),
    functional: groupRequirements(dataset.rf, (r) => r.module, "rf"),
    nonFunctional: groupRequirements(dataset.rnf, (r) => r.category, "rnf"),
  };
}
