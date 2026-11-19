#!/bin/sh
set -eu

# Debian Trixie has postponed CVE-2026-86140. Backport GNOME's upstream fix
# (d1686f91dbda141a752200419d35639fd6b38340) to its ABI-compatible source.
apt-get update
apt-get install -y --no-install-recommends \
    autoconf automake build-essential ca-certificates dpkg-dev libtool m4 \
    patch perl pkg-config zlib1g-dev

printf 'deb-src http://deb.debian.org/debian trixie main\n' \
    > /etc/apt/sources.list.d/rfis-deb-src.list
apt-get update

source_version="$(dpkg-query -W -f='${Version}' libxml2)"
cd /tmp
apt-get source "libxml2=${source_version}"
source_dir="$(find /tmp -mindepth 1 -maxdepth 1 -type d -name 'libxml2-*' -print -quit)"
test -n "$source_dir"
cd "$source_dir"

if ! grep -q 'size - len < 50' valid.c; then
    patch --fuzz=0 -p1 < /tmp/CVE-2026-86140.patch
fi

autoreconf -fi
./configure \
    --prefix=/usr \
    --libdir="/usr/lib/$(gcc -print-multiarch)" \
    --disable-static \
    --enable-shared \
    --with-legacy \
    --with-zlib \
    --without-python \
    --without-icu \
    --without-readline \
    --without-docs
make -j"$(nproc)"
make install DESTDIR=/tmp/libxml-root

multiarch="$(gcc -print-multiarch)"
mkdir -p "/tmp/pkgroot/usr/lib/$multiarch" /tmp/pkgroot/DEBIAN
cp -a "/tmp/libxml-root/usr/lib/$multiarch"/libxml2.so.2* "/tmp/pkgroot/usr/lib/$multiarch/"
package_version="$(dpkg-parsechangelog -S Version)+rfis1"
printf 'Package: libxml2\nVersion: %s\nArchitecture: %s\nMaintainer: RFIS build\nDepends: libc6 (>= 2.34), zlib1g\nDescription: Debian libxml2 with upstream CVE-2026-86140 bounds check\n' \
    "$package_version" "$(dpkg --print-architecture)" > /tmp/pkgroot/DEBIAN/control
dpkg-deb --build --root-owner-group /tmp/pkgroot /tmp/libxml2-fixed.deb
dpkg -i /tmp/libxml2-fixed.deb
ldconfig
php /usr/local/libexec/verify-bounded-dtd-error.php

apt-get clean
find /tmp -mindepth 1 -maxdepth 1 -type d -name 'libxml2-*' -exec rm -rf {} +
rm -rf /var/lib/apt/lists/* /tmp/pkgroot /tmp/libxml-root
