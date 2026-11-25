#!/usr/bin/env bash
#
# 打发布包：用 git archive 导出一次干净的构建（自动尊重 .gitattributes 里的 export-ignore）。
# 用法：bin/build.sh
#
set -euo pipefail

cd "$(dirname "$0")/.."

VERSION="$(grep -m1 -E '^ \* Version:' crossborder-shipping-rates.php | sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r')"

if [ -z "${VERSION}" ]; then
	echo "错误：无法从 crossborder-shipping-rates.php 的插件头读取版本号" >&2
	exit 1
fi

if [ -n "$(git status --porcelain)" ]; then
	echo "警告：工作区有未提交的改动，发布包只会包含已提交的内容（HEAD）。" >&2
fi

mkdir -p dist
OUT="dist/crossborder-shipping-rates-${VERSION}.zip"
rm -f "${OUT}"

git archive \
	--format=zip \
	--prefix="crossborder-shipping-rates/" \
	-o "${OUT}" \
	HEAD

echo "已生成 ${OUT}"
echo "  版本：${VERSION}"
echo "  大小：$(du -h "${OUT}" | cut -f1)"
