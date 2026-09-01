/**
 * RemTreeNode.tsx
 *
 * Recursive tree node used by `RemSidebar.tsx` (REQ-09 tree semantics).
 *
 * Two visual modes per spec §3 / design §9:
 *
 *   - Group (RF/RNF by module/category): `role="treeitem"` +
 *     `aria-expanded`, `aria-level`. Clicking the header toggles
 *     `expanded` state in the parent.
 *
 *   - Leaf (selectable RF / RNF / stakeholder): `role="treeitem"` +
 *     `aria-selected` (focused) + `aria-current="true"` when this
 *     leaf is the active selection.
 *
 * Memoized on `node.id` so React 19's Concurrent Mode reuses the
 * subtrees when only one leaf flips its `aria-current`. Per AGENTS.md
 * §18, dynamic-interactive state stays in `.tsx` while the static
 * shell stays in `.astro`.
 */

import { memo, useCallback, useState, type ReactElement, type ReactNode } from 'react';

import type { TreeNode } from '../../lib/rem/types';
import type { useTranslations } from '../../i18n';

/**
 * Translator shape from the i18n hook. Mirrors the type alias in
 * `RemSidebar.tsx` so the prop signature is consistent across the
 * sidebar subtree (was `(key: string) => string` before Batch 4).
 */
type Translator = ReturnType<typeof useTranslations>['t'];

export interface RemTreeNodeProps {
  /** Tree node to render — see `lib/rem/types.ts` `TreeNode`. */
  node: TreeNode;
  /** Current depth in the tree (1 = top level). */
  level: number;
  /** UID of the currently-selected leaf (or null when nothing is picked). */
  selectedUid: string | null;
  /**
   * Fired when the user activates a leaf (click / Enter / Space).
   * Groups ignore this — they only toggle themselves.
   */
  onSelect: (uid: string) => void;
  /** Translator — mirrors the i18n hook's `t` shape. */
  t: Translator;
  /**
   * v2 slot: per-row action UI (kebab menu). Rendered as the last
   * child of the leaf when present. The parent (RemSidebar) wires the
   * onEdit / onDelete callbacks.
   */
  actions?: ReactNode;
}

function isLeaf(node: TreeNode): boolean {
  return node.children === undefined || node.children.length === 0;
}

function RemTreeNodeInner({
  node,
  level,
  selectedUid,
  onSelect,
  t,
  actions,
}: RemTreeNodeProps): ReactElement {
  const leaf = isLeaf(node);
  const [expanded, setExpanded] = useState(true);

  const handleClick = useCallback(() => {
    if (leaf && node.data !== undefined) {
      onSelect(node.data.uid);
      return;
    }
    setExpanded((prev) => !prev);
  }, [leaf, node.data, onSelect]);

  const handleKeyDown = useCallback(
    (event: React.KeyboardEvent<HTMLLIElement>) => {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }
      event.preventDefault();
      handleClick();
    },
    [handleClick]
  );

  const isSelected = leaf && selectedUid !== null && node.data?.uid === selectedUid;

  const baseClass =
    'rem-tree-node flex w-full items-center gap-1.5 rounded-md border border-transparent px-2 py-1.5 text-left text-sm transition-colors hover:border-slate-200 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]';
  // v2 (REQ-24#1): selected leaf gets the teal accent palette — left
  // border indicator + soft teal background. Hover transitions keep
  // the affordance visible from idle through focus.
  const selectedClass =
    'rem-tree-node--selected border-l-[3px] border-l-[#2D9D8E] bg-[#2D9D8E]/12 pl-[5px] font-semibold text-[#1E6F62]';

  if (leaf) {
    return (
      <li
        role="treeitem"
        aria-level={level}
        aria-selected={isSelected === true}
        aria-current={isSelected === true ? 'true' : undefined}
        data-rem-leaf
        data-rem-leaf-id={node.data?.uid ?? node.id}
        data-testid={`rem-leaf-${node.data?.uid ?? node.id}`}
        className={`group ${isSelected === true ? `${baseClass} ${selectedClass}` : baseClass}`}
        tabIndex={0}
        onClick={handleClick}
        onKeyDown={handleKeyDown}
      >
        <span
          className="rem-tree-node__label min-w-0 flex-1 truncate"
          title={node.label}
        >
          {node.label}
        </span>
        {actions !== undefined ? (
          <span className="rem-tree-node__actions shrink-0">{actions}</span>
        ) : null}
        <span className="rem-tree-node__uid shrink-0 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] uppercase tracking-wide text-slate-500 group-hover:bg-slate-200">
          {node.data !== undefined ? shortCode(node.data.uid) : ''}
        </span>
      </li>
    );
  }

  return (
    <li
      role="treeitem"
      aria-level={level}
      aria-expanded={expanded}
      data-rem-group
      data-rem-group-id={node.id}
      className="rem-tree-node rem-tree-node--group mt-1"
    >
      <button
        type="button"
        aria-expanded={expanded}
        aria-controls={`rem-tree-group-${node.id}`}
        onClick={() => setExpanded((prev) => !prev)}
        className={baseClass}
      >
        <span className="rem-tree-node__label font-semibold text-slate-800">{node.label}</span>
        <span aria-hidden="true" className="rem-tree-node__caret text-slate-400">
          {expanded ? '▾' : '▸'}
        </span>
      </button>
      <ul
        role="group"
        id={`rem-tree-group-${node.id}`}
        aria-label={t('rem.sidebar.heading')}
        className="rem-tree-node__children ml-3 mt-1 space-y-0.5 border-l border-slate-200 pl-2"
      >
        {node.children?.map((child) => (
          <RemTreeNode
            key={child.id}
            node={child}
            level={level + 1}
            selectedUid={selectedUid}
            onSelect={onSelect}
            t={t}
          />
        ))}
      </ul>
    </li>
  );
}

/**
 * Strip the long Doorstop prefix (`stakeholder_001` → `s_001`) for
 * stakeholder leaves where `node.label` is the human name. RF / RNF
 * leaves already carry their `RF-AUTH-001` code as the label.
 */
function shortCode(uid: string): string {
  if (uid.startsWith('stakeholder_')) {
    return `s_${uid.slice('stakeholder_'.length)}`;
  }
  return uid;
}

/**
 * Memoized so re-renders are cheap when only one leaf's
 * `aria-current` flips. The component is otherwise pure.
 */
export const RemTreeNode = memo(RemTreeNodeInner);
RemTreeNode.displayName = 'RemTreeNode';

export default RemTreeNode;
