#!/bin/sh
apk add --no-cache -q curl unzip >/dev/null
measure() {
  dir=$1; name=$2; ver=$3; zip=$4
  printf '%s|%s|%s|%s|%s|%s|%s\n' "$name" "$ver" "$zip" \
    "$(( $(find "$dir" -type f -exec stat -c %s {} + | awk '{s+=$1} END {print s}') / 1024 ))" \
    "$(find "$dir" -type f | wc -l)" \
    "$(find "$dir" -name '*.php' | wc -l)" \
    "$(find "$dir" -name '*.php' -exec cat {} + | wc -l)"
}
echo "plugin|version|zip_kb|unpacked_kb|files|php_files|php_lines"
for s in schema-and-structured-data-for-wp all-in-one-schemaorg-rich-snippets wp-seo-structured-data-schema schema; do
  rm -rf /tmp/p && mkdir -p /tmp/p && cd /tmp/p
  ver=$(curl -s "https://api.wordpress.org/plugins/info/1.0/$s.json" | grep -o '"version":"[^"]*"' | head -1 | cut -d\" -f4)
  curl -sL -o /tmp/p.zip "https://downloads.wordpress.org/plugin/$s.latest-stable.zip"
  zkb=$(( $(stat -c %s /tmp/p.zip) / 1024 ))
  unzip -q /tmp/p.zip -d /tmp/p
  measure /tmp/p "$s" "$ver" "$zkb"
done
ours=$(ls /build/unlimited-schema-*.zip | sort -V | tail -1)
ver=$(basename "$ours" .zip | sed 's/unlimited-schema-//')
rm -rf /tmp/p && mkdir -p /tmp/p && unzip -q "$ours" -d /tmp/p
measure /tmp/p unlimited-schema "$ver" $(( $(stat -c %s "$ours") / 1024 ))
