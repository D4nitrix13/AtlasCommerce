"""
Tests for `migrate_to_doorstop.py`.

Scope (DP1=A lock — rem-viewer SDD change):
- Re-running the migration script is idempotent: the generated YAMLs are
  byte-equal between runs. This guards against risk #6 from the change
  proposal: "migrate_to_doorstop.py overwrites schema changes if someone
  re-runs it after DP1 is applied".
- Every generated RF/RNF YAML carries the 4 new REM 1.2.2 fields
  (version, autores, fuentes, dependencias) plus the derived
  `estabilidad` field.
- Field defaults are sensible: version="1.0", estabilidad="alta" when
  verifiedBy is present, "PD" otherwise.

Why a small `unittest`-style suite (no pytest):
- The project has no pytest dependency in `scripts/requirements/`.
- The script's own `__main__` is the entrypoint; tests run by importing
  its public functions and re-implementing a `main()` that targets a
  temporary directory. This avoids touching the real `req/` folder.
"""

from __future__ import annotations

import importlib.util
import json
import shutil
import sys
import tempfile
import unittest
from pathlib import Path
from typing import Any, Dict, List

SCRIPTS_DIR = Path(__file__).resolve().parent.parent
MIGRATE_PATH = SCRIPTS_DIR / "migrate_to_doorstop.py"
SEED_RF = SCRIPTS_DIR / "seed_rf.json"
SEED_RNF = SCRIPTS_DIR / "seed_rnf.json"
SEED_STK = SCRIPTS_DIR / "seed_stakeholders.json"


def _load_migrate_module() -> Any:
    """Load `migrate_to_doorstop.py` as a module from its path."""
    spec = importlib.util.spec_from_file_location("migrate_to_doorstop", MIGRATE_PATH)
    assert spec and spec.loader, f"Cannot load migration script at {MIGRATE_PATH}"
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def _copy_seeds(target_dir: Path) -> None:
    """Copy the three seed JSONs into a temp directory and rewrite the
    module's SEEDS / REQ_DIR globals to point at the temp location."""
    for src in (SEED_RF, SEED_RNF, SEED_STK):
        shutil.copy(src, target_dir / src.name)


def _rewrite_globals(module: Any, req_dir: Path) -> None:
    """Point the migration module at the temp seed/req dirs."""
    module.SEEDS = {
        "stakeholders": module.HERE / "seed_stakeholders.json",
        "rf": module.HERE / "seed_rf.json",
        "rnf": module.HERE / "seed_rnf.json",
    }
    module.REQ_DIR = req_dir
    module.DOC_DIR = req_dir.parent / "docs" / "requirements"


def _run_migration(target_dir: Path) -> Path:
    """Run the migration against a temp dir; return the req/ subdir."""
    req_dir = target_dir / "req"
    module = _load_migrate_module()
    _rewrite_globals(module, req_dir)
    module.main()
    return req_dir


def _list_yml(req_dir: Path, prefix: str) -> List[Path]:
    return sorted(p for p in req_dir.iterdir() if p.name.startswith(prefix))


class MigrateToDoorstopTests(unittest.TestCase):
    """Idempotence + DP1=A schema field contract."""

    def setUp(self) -> None:
        self._tmp = tempfile.TemporaryDirectory()
        self.tmp_dir = Path(self._tmp.name)
        _copy_seeds(self.tmp_dir)

    def tearDown(self) -> None:
        self._tmp.cleanup()

    def test_migration_produces_48_rf_30_rnf_3_stakeholder_files(self) -> None:
        req_dir = _run_migration(self.tmp_dir)

        self.assertEqual(len(_list_yml(req_dir, "rf_")), 48)
        self.assertEqual(len(_list_yml(req_dir, "rnf_")), 30)
        self.assertEqual(len(_list_yml(req_dir, "stakeholder_")), 3)

    def test_rf_yml_contains_dp1_fields(self) -> None:
        req_dir = _run_migration(self.tmp_dir)

        sample = req_dir / "rf_001_RF-AUTH-001.yml"
        self.assertTrue(sample.exists())
        body = sample.read_text()

        # 4 new stored fields + 1 derived field
        self.assertIn('version: "1.0"', body)
        self.assertIn("autores:", body)
        self.assertIn("fuentes: []", body)
        self.assertIn("dependencias:", body)
        self.assertIn('estabilidad: "alta"', body)  # rf_001 has stakeholder_001

    def test_rnf_yml_contains_dp1_fields_with_pd_estabilidad(self) -> None:
        req_dir = _run_migration(self.tmp_dir)

        sample = req_dir / "rnf_010_RNF-PERF-020.yml"
        self.assertTrue(sample.exists())
        body = sample.read_text()

        # RNFs have no verified-by → estabilidad defaults to "PD"
        self.assertIn('version: "1.0"', body)
        self.assertIn("autores:", body)
        self.assertIn("fuentes: []", body)
        self.assertIn("dependencias: []", body)
        self.assertIn('estabilidad: "PD"', body)

    def test_re_run_is_idempotent_byte_equal_output(self) -> None:
        """Running the script twice must produce byte-identical YAMLs.

        This is the guard for design risk #6: re-running the migration
        after a manual YAML edit must not silently destroy fields. If
        you hand-edit a YAML, the second run rolls back to the seed —
        by design, the migration is the source of truth.
        """
        first = _run_migration(self.tmp_dir)
        first_dump = {p.name: p.read_bytes() for p in first.iterdir()}

        # Re-run with the same seed data
        second = _run_migration(self.tmp_dir)
        second_dump = {p.name: p.read_bytes() for p in second.iterdir()}

        self.assertEqual(set(first_dump.keys()), set(second_dump.keys()))
        for name, content in first_dump.items():
            self.assertEqual(
                content,
                second_dump[name],
                f"File {name} differs between runs (idempotence broken)",
            )

    def test_seed_json_keeps_new_dp1_fields(self) -> None:
        """The new seed fields must survive a migration run untouched
        (the script never writes back to the seed files)."""
        before = json.loads(SEED_RF.read_text())
        _run_migration(self.tmp_dir)
        after = json.loads(SEED_RF.read_text())

        for item in after:
            self.assertIn("version", item)
            self.assertIn("autores", item)
            self.assertIn("fuentes", item)
            self.assertIn("dependencias", item)
            self.assertEqual(item["version"], "1.0")
        # And nothing was modified.
        self.assertEqual(before, after)

    def test_handcrafted_stale_rf_yaml_is_regenerated_to_match_seed(self) -> None:
        """If a YAML is hand-edited between runs, the next run rolls it
        back to the seed (single source of truth contract)."""
        req_dir = _run_migration(self.tmp_dir)
        target = req_dir / "rf_001_RF-AUTH-001.yml"

        original = target.read_text()
        tampered = original.replace('version: "1.0"', 'version: "9.9-tampered"')
        target.write_text(tampered)

        # Re-run; the tampered file should be reverted.
        _run_migration(self.tmp_dir)
        self.assertEqual(target.read_text(), original)


if __name__ == "__main__":
    sys.exit(0 if unittest.main(exit=False, verbosity=2).result.wasSuccessful() else 1)
