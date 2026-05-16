"""
Replace all red colour variants across the project with the brand red #C0392B.

Run from the project root:
    python replace_red_colors.py
"""

import os
import re

PROJECT_DIR = os.path.dirname(os.path.abspath(__file__))
TARGET = '#C0392B'
EXTENSIONS = {'.css', '.php', '.js', '.html'}
SKIP_DIRS = {'.git', 'vendor', 'node_modules'}

# ── Colours to replace (case-insensitive matching) ────────────────────────────
# All confirmed red colours found in the project.
# Excluded intentional design tints, pinks, salmons, and browns.
RED_COLORS = [
    # Very dark reds
    '#7B241C', '#800000', '#8B0000', '#8B2921', '#8C2016',
    # Dark reds
    '#943126', '#962D23', '#990000',
    '#A93226',
    '#B02020', '#B03228', '#B03A2E', '#B22222', '#B30000',
    # Short 3-char reds
    '#A00', '#C00',
    # Mid reds
    '#C0000D', '#C11424', '#C82020', '#C8332D', '#C92F2F', '#CC0000',
    '#D03530', '#D92020',
    # Bright / vivid reds
    '#DC143C', '#DC2626',
    '#E03030', '#E03535', '#E3000F', '#E60000', '#E62020', '#E63946',
    '#E74C3C', '#E8192C', '#E8272A',
    '#F04040', '#F44336',
    '#FF0000', '#FF1A1A', '#FF3333', '#FF4D4D', '#FF5050',
    '#FF6B6B',
]

# Build case-insensitive lookup: normalised-upper → original (for display)
RED_SET = {c.upper(): c for c in RED_COLORS}


def build_pattern(colors):
    """Return a compiled regex that matches any colour in *colors* (case-insensitive)."""
    # Sort longest first to avoid partial matches (#ff4d4d before #ff4d, etc.)
    sorted_colors = sorted(colors, key=len, reverse=True)
    escaped = [re.escape(c) for c in sorted_colors]
    return re.compile('|'.join(escaped), re.IGNORECASE)


PATTERN = build_pattern(list(RED_SET.keys()))


def replace_in_file(path):
    try:
        with open(path, 'r', encoding='utf-8', errors='ignore') as fh:
            original = fh.read()
    except Exception as e:
        print(f'  [SKIP] Cannot read {path}: {e}')
        return 0

    new_content, count = PATTERN.subn(TARGET, original)

    if count:
        with open(path, 'w', encoding='utf-8', errors='ignore') as fh:
            fh.write(new_content)

    return count


def main():
    total_files = 0
    total_replacements = 0

    print(f'Replacing {len(RED_SET)} red colour variants -> {TARGET}')
    print(f'Scanning: {PROJECT_DIR}\n')

    for root, dirs, files in os.walk(PROJECT_DIR):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
        for fname in files:
            ext = os.path.splitext(fname)[1].lower()
            if ext not in EXTENSIONS:
                continue
            full_path = os.path.join(root, fname)
            n = replace_in_file(full_path)
            if n:
                rel = os.path.relpath(full_path, PROJECT_DIR)
                print(f'  [{n:>3} replacement{"s" if n>1 else " "}]  {rel}')
                total_files += 1
                total_replacements += n

    print(f'\nDone! {total_replacements} replacement(s) in {total_files} file(s).')


if __name__ == '__main__':
    main()
