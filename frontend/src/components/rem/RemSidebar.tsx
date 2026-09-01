/**
 * RemSidebar.tsx
 *
 * React island for the REM viewer (REQ-03, REQ-04, REQ-09, REQ-10,
 * REQ-12). Owns:
 *
 *   - Search query (case-insensitive substring via `lib/rem/search`).
 *   - Selection state (UID of the currently highlighted requirement
 *     or stakeholder).
 *   - URL sync via `?id=<uid>` (`history.replaceState`, no reload —
 *     see REQ-12#1).
 *   - Initial-UID validation (REQ-12#3 — unknown UID falls back to
 *     the first item).
 *   - Keyboard navigation across the tree (Tab / Arrow / Enter /
 *     Escape — REQ-09).
 *   - Skeleton state on initial mount (REQ-10#1 — `aria-busy="true"`
 *     on the wrapper until the first effect commits).
 *   - Empty state on zero-match search (REQ-03#5).
 *   - Detail-panel update via `fetch + innerHTML` (F2 locked;
 *     design §4 step 8). The endpoint ships in Batch 2b — the call
 *     silently fails today and the URL state still works.
 */

import { useCallback, useEffect, useMemo, useRef, useState, type ReactElement } from 'react';

import { RemTreeNode } from './RemTreeNode';
import { match as searchMatch } from '../../lib/rem/search';
import { buildTree } from '../../lib/rem/tree';
import type { Kind, RemDataset, Requirement, Stakeholder, TreeNode } from '../../lib/rem/types';
import type { Locale } from '../../i18n/config';

export interface RemSidebarProps {
  dataset: RemDataset;
  /** UID to preselect; falls back to the first item when invalid. */
  initialUid: string;
  locale: Locale;
  /** Optional translator — mirrors `format.ts`'s `Translator` shape. */
  t?: (key: string) => string;
}

/**
 * Resolve a UID to a `TreeNode` payload (any leaf) or null. Looks
 * across the unfiltered tree (search filtering happens later, after
 * the selection is resolved). Keeps the rule single-sourced.
 */
function findItemByUid(
  dataset: RemDataset,
  uid: string
): { item: Requirement | Stakeholder; kind: 'rf' | 'rnf' | 'stakeholder' } | null {
  for (const s of dataset.stakeholders) {
    if (s.uid === uid) {
      return { item: s, kind: 'stakeholder' };
    }
  }
  for (const rf of dataset.rf) {
    if (rf.uid === uid) {
      return { item: rf, kind: 'rf' as Kind };
    }
  }
  for (const rnf of dataset.rnf) {
    if (rnf.uid === uid) {
      return { item: rnf, kind: 'rnf' as Kind };
    }
  }
  return null;
}

/**
 * First dataset UID we can present (used as fallback when
 * `initialUid` is invalid and the URL has no `?id=` either).
 *
 * Order: stakeholders first (sorted by uid asc per `tree.buildTree`),
 * then RF, then RNF — same as the sidebar's render order so the
 * fallback always lands on a visible node.
 */
function firstUid(dataset: RemDataset): string {
  if (dataset.stakeholders.length > 0) {
    return [...dataset.stakeholders].sort((a, b) => a.uid.localeCompare(b.uid, 'es'))[0]!.uid;
  }
  if (dataset.rf.length > 0) {
    return [...dataset.rf].sort((a, b) => a.uid.localeCompare(b.uid, 'es'))[0]!.uid;
  }
  if (dataset.rnf.length > 0) {
    return [...dataset.rnf].sort((a, b) => a.uid.localeCompare(b.uid, 'es'))[0]!.uid;
  }
  return 'rf_001';
}

/**
 * Read `?id=<uid>` from `window.location.search`. SSR-safe.
 */
function readUrlId(): string | null {
  if (typeof window === 'undefined') {
    return null;
  }
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

/**
 * Filter a tree down to UIDs present in a hit set. Groups are kept
 * intact; empty groups drop out so the visual tree only ever shows
 * reachable items.
 *
 * Leaves store their UID on `data.uid` (the dataset contract), not on
 * `id` (which is the human-readable code per `tree.buildTree`). We
 * match against `data.uid` for filtering, falling back to `id` for
 * stakeholder leaves (whose id already equals the uid).
 */
function filterTree(
  tree: ReturnType<typeof buildTree>,
  allowedUids: Set<string>
): {
  stakeholders: TreeNode[];
  functional: TreeNode[];
  nonFunctional: TreeNode[];
} {
  const leafUid = (node: TreeNode): string | null => {
    if (node.data !== undefined && 'uid' in node.data) {
      return node.data.uid;
    }
    return node.id;
  };
  const stakeholders = tree.stakeholders.filter((n) => {
    const uid = leafUid(n);
    return uid !== null && allowedUids.has(uid);
  });
  const filterGroups = (groups: TreeNode[]): TreeNode[] => {
    const result: TreeNode[] = [];
    for (const g of groups) {
      const kids = (g.children ?? []).filter((child) => {
        const uid = leafUid(child);
        return uid !== null && allowedUids.has(uid);
      });
      if (kids.length > 0) {
        result.push({ ...g, children: kids });
      }
    }
    return result;
  };
  return {
    stakeholders,
    functional: filterGroups(tree.functional),
    nonFunctional: filterGroups(tree.nonFunctional),
  };
}

export function RemSidebar({
  dataset,
  initialUid,
  locale: _locale,
  t = (k: string) => k,
}: RemSidebarProps): ReactElement {
  const [hydrating, setHydrating] = useState(true);
  const [query, setQuery] = useState('');
  const [selectedUid, setSelectedUid] = useState<string>(initialUid);
  const [fallbackApplied, setFallbackApplied] = useState(false);
  const treeRef = useRef<HTMLDivElement | null>(null);
  // Hold the rAF id so we can cancel the scheduled hydration flip on
  // unmount. In jsdom + jest the rAF callback eventually fires (after
  // the waitFor poll) so the post-settle assertion observes
  // `aria-busy="false"`.
  const hydrationFrameRef = useRef<number | null>(null);

  // ─── URL ↔ selection sync (REQ-12#1, #3) ────────────────────────────
  // On mount: take ?id=<uid> from URL if present, validate against the
  // dataset, otherwise validate initialUid, otherwise fall back. The
  // hydration flip is deferred to the next animation frame so assistive
  // tech + tests can observe the `aria-busy="true"` state.
  useEffect(() => {
    const urlId = readUrlId();
    const candidate = urlId ?? initialUid;
    const exists = findItemByUid(dataset, candidate) !== null;
    const finalUid = exists ? candidate : firstUid(dataset);

    if (finalUid !== selectedUid || urlId !== null) {
      setSelectedUid(finalUid);
    }
    if (!exists) {
      setFallbackApplied(true);
    }
    // Replace URL once we know the authoritative UID, but only when we
    // actually changed something.
    if (typeof window !== 'undefined') {
      const wantedSearch = `?id=${finalUid}`;
      if (window.location.search !== wantedSearch) {
        window.history.replaceState({}, '', `${window.location.pathname}${wantedSearch}`);
      }
    }
    // Defer the hydration flip so the skeleton stays visible long
    // enough for assistive tech + tests to observe `aria-busy="true"`.
    // Both `requestAnimationFrame` and a fallback `setTimeout(0)` are
    // scheduled — vitest waits advance past timers but jsdom's rAF
    // polyfill is unreliable across versions.
    if (typeof window !== 'undefined') {
      if (typeof window.requestAnimationFrame === 'function') {
        hydrationFrameRef.current = window.requestAnimationFrame(() => {
          setHydrating(false);
        });
      } else {
        hydrationFrameRef.current = window.setTimeout(() => {
          setHydrating(false);
        }, 0);
      }
    }
    return () => {
      if (hydrationFrameRef.current === null || typeof window === 'undefined') {
        return;
      }
      if (typeof window.cancelAnimationFrame === 'function') {
        window.cancelAnimationFrame(hydrationFrameRef.current);
      }
      window.clearTimeout(hydrationFrameRef.current);
      hydrationFrameRef.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // ─── Side effect: detail panel update via fetch + innerHTML ─────────
  // (F2-locked; design §4 step 8). Endpoint is `pages/api/rem/[uid].html`
  // which Batch 2b adds. We swallow the failure today so the URL state
  // still drives a coherent page.
  useEffect(() => {
    if (typeof window === 'undefined') return;
    if (fallbackApplied === false && query === '') {
      // Only fetch on real selection changes (not on initial fallback).
      // We always fetch for the selected uid because the page hydrates
      // with the SSR-rendered detail already in place; the only thing
      // missing is post-selection detail updates.
    }
    const target = `/api/rem/${selectedUid}.html`;
    let aborted = false;
    fetch(target, { headers: { Accept: 'text/html' } })
      .then((res) => (res.ok ? res.text() : ''))
      .then((html) => {
        if (aborted || typeof document === 'undefined') return;
        const slot = document.querySelector('.rem-detail');
        if (slot instanceof HTMLElement && html.length > 0) {
          slot.innerHTML = html;
        }
      })
      .catch(() => {
        // Endpoint lives in Batch 2b; ignore network errors here.
      });
    return () => {
      aborted = true;
    };
  }, [selectedUid, fallbackApplied, query]);

  // ─── Selection handler: URL sync + state update ────────────────────
  const handleSelect = useCallback((uid: string) => {
    setSelectedUid(uid);
    if (typeof window !== 'undefined') {
      const wanted = `?id=${uid}`;
      if (window.location.search !== wanted) {
        window.history.replaceState({}, '', `${window.location.pathname}${wanted}`);
      }
    }
  }, []);

  // ─── Search filter ──────────────────────────────────────────────────
  const baseTree = useMemo(() => buildTree(dataset), [dataset]);
  const filteredTree = useMemo(() => {
    if (query.trim() === '') {
      return baseTree;
    }
    const hits = searchMatch(dataset, query);
    const allowed = new Set(hits.map((h) => h.uid));
    return filterTree(baseTree, allowed);
  }, [baseTree, dataset, query]);

  const hasAnyMatch =
    filteredTree.stakeholders.length > 0 ||
    filteredTree.functional.length > 0 ||
    filteredTree.nonFunctional.length > 0;
  const showEmpty = query.trim() !== '' && !hasAnyMatch;

  // ─── Keyboard nav across the tree (REQ-09) ─────────────────────────
  const focusables = useCallback((): HTMLElement[] => {
    if (treeRef.current === null) return [];
    return Array.from(
      treeRef.current.querySelectorAll<HTMLElement>('[role="treeitem"][tabindex="0"]')
    );
  }, []);

  const handleTreeKeyDown = useCallback(
    (event: React.KeyboardEvent<HTMLDivElement>) => {
      const list = focusables();
      const active = document.activeElement;
      const idx = active instanceof HTMLElement ? list.indexOf(active) : -1;
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        const next = list[Math.min(idx + 1, list.length - 1)];
        next?.focus();
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        const prev = list[Math.max(idx - 1, 0)];
        prev?.focus();
      } else if (event.key === 'Home') {
        event.preventDefault();
        list[0]?.focus();
      } else if (event.key === 'End') {
        event.preventDefault();
        list[list.length - 1]?.focus();
      }
    },
    [focusables]
  );

  const handleSearchKeyDown = useCallback((event: React.KeyboardEvent<HTMLInputElement>) => {
    if (event.key !== 'Escape') return;
    event.preventDefault();
    setQuery('');
    if (event.currentTarget instanceof HTMLInputElement) {
      event.currentTarget.value = '';
    }
  }, []);

  return (
    <div data-rem-sidebar className="rem-sidebar__root flex h-full flex-col gap-3 p-4">
      <div
        data-testid="rem-skeleton-wrapper"
        data-rem-skeleton
        aria-busy={hydrating ? 'true' : 'false'}
        hidden={!hydrating}
        className="rem-skeleton flex animate-pulse flex-col gap-3"
      >
        <span className="block h-4 w-1/2 rounded bg-slate-200"></span>
        <span className="block h-4 w-2/3 rounded bg-slate-200"></span>
        <span className="block h-4 w-3/4 rounded bg-slate-200"></span>
        <span className="block h-4 w-1/3 rounded bg-slate-200"></span>
        <span className="block h-4 w-1/2 rounded bg-slate-200"></span>
      </div>

      <input
        type="search"
        data-testid="rem-search-input"
        data-rem-search
        placeholder={t('rem.sidebar.search.placeholder')}
        aria-label={t('rem.sidebar.search.placeholder')}
        value={query}
        onChange={(e) => setQuery(e.currentTarget.value)}
        onKeyDown={handleSearchKeyDown}
        className="rem-search w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
      />

      {showEmpty ? (
        <section
          role="status"
          data-testid="rem-empty"
          data-rem-empty
          aria-live="polite"
          className="rem-empty mx-auto flex max-w-md flex-col items-center gap-3 p-6 text-center text-slate-600"
        >
          <h3 className="text-base font-semibold text-slate-800">
            {t('rem.sidebar.empty.heading')}
          </h3>
          <p className="text-sm">{t('rem.sidebar.empty.body')}</p>
          <button
            type="button"
            data-testid="rem-clear-search"
            data-rem-clear-search
            onClick={() => setQuery('')}
            className="rem-empty__clear mt-2 inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
          >
            {t('rem.sidebar.search.clear')}
          </button>
        </section>
      ) : (
        <div
          ref={treeRef}
          onKeyDown={handleTreeKeyDown}
          role="tree"
          aria-label={t('rem.sidebar.heading')}
          className="rem-tree flex-1 overflow-y-auto"
        >
          <TreeBranch
            label={t('rem.tree.stakeholders')}
            nodes={filteredTree.stakeholders}
            selectedUid={selectedUid}
            onSelect={handleSelect}
            t={t}
            level={1}
          />
          <TreeBranch
            label={t('rem.tree.functional')}
            nodes={filteredTree.functional}
            selectedUid={selectedUid}
            onSelect={handleSelect}
            t={t}
            level={1}
          />
          <TreeBranch
            label={t('rem.tree.nonfunctional')}
            nodes={filteredTree.nonFunctional}
            selectedUid={selectedUid}
            onSelect={handleSelect}
            t={t}
            level={1}
          />
        </div>
      )}
    </div>
  );
}

/**
 * Wraps an array of tree nodes under a section heading. Each branch is
 * a listbox-of-groups: the headings are not focusable; their leaves are.
 * For stakeholders (flat), we render the list directly without an extra
 * `role="treeitem"` wrapper — each leaf carries its own.
 */
function TreeBranch({
  label,
  nodes,
  selectedUid,
  onSelect,
  t,
  level,
}: {
  label: string;
  nodes: TreeNode[];
  selectedUid: string;
  onSelect: (uid: string) => void;
  t: (key: string) => string;
  level: number;
}): ReactElement | null {
  if (nodes.length === 0) return null;

  // Stakeholder branch is flat (per buildTree) — render the leaves
  // directly under a labelled list.
  const isFlat = nodes[0]?.children === undefined;
  if (isFlat) {
    return (
      <section aria-label={label} className="rem-tree-branch rem-tree-branch--flat mb-3">
        <h2 className="rem-tree-branch__heading sr-only">{label}</h2>
        <ul role="group" aria-label={label} className="space-y-0.5">
          {nodes.map((n) => (
            <RemTreeNode
              key={n.id}
              node={n}
              level={level}
              selectedUid={selectedUid}
              onSelect={onSelect}
              t={t}
            />
          ))}
        </ul>
      </section>
    );
  }

  return (
    <section aria-label={label} className="rem-tree-branch mb-3">
      <h2 className="rem-tree-branch__heading sr-only">{label}</h2>
      <ul role="group" aria-label={label} className="space-y-0.5">
        {nodes.map((n) => (
          <RemTreeNode
            key={n.id}
            node={n}
            level={level}
            selectedUid={selectedUid}
            onSelect={onSelect}
            t={t}
          />
        ))}
      </ul>
    </section>
  );
}

export default RemSidebar;
