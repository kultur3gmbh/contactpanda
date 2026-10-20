#!/usr/bin/env python3
"""Regression test for duplicate translations in development catalogs."""

import importlib.util
import pathlib
import tempfile


ROOT = pathlib.Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("compile_mo", ROOT / "tools" / "compile_mo.py")
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)

with tempfile.TemporaryDirectory() as directory:
    catalog = pathlib.Path(directory) / "duplicate.po"
    catalog.write_text(
        'msgid ""\nmsgstr ""\n\n'
        'msgid "Layout"\nmsgstr "Ansicht"\n\n'
        'msgid "Layout"\nmsgstr ""\n',
        encoding="utf-8",
    )
    messages = MODULE.parse_po(catalog)
    if messages.get("Layout") != "Ansicht":
        raise SystemExit("An empty duplicate replaced a usable translation.")

print("Translation compiler duplicate handling passed")
