OUT="$HOME/Desktop/nomad_code_dump.txt"
: > "$OUT"

find . -type f \
  \( -name "*.html" -o -name "*.css" -o -name "*.js" -o -name "*.php" \) \
  ! -name "update_*.php" \
  ! -name "*.bak*" \
  ! -path "*/.git/*" \
  ! -path "*/node_modules/*" \
| sort \
| while IFS= read -r file; do
    echo "📄 Menambahkan: ${file#./}" >&2
    {
      echo "===== START FILE: ${file#./} ====="
      cat "$file"
      echo ""
      echo "===== END FILE: ${file#./} ====="
      echo ""
    } >> "$OUT"
  done

pbcopy < "$OUT"
echo "✅ Selesai! Total baris: $(wc -l < "$OUT" | tr -d ' ') | File: $OUT (sudah dicopy ke clipboard)"