#!/usr/bin/python3
# -*- coding: utf-8 -*-
#
# This script attempts to convert Linux system accounts into the
# RestAuth data import format[1].
#
# [1] https://server.restauth.net/migrate/import-format.html
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

import os, sys, json, argparse, hashlib, pprint, base64, binascii

data = {}

parser = argparse.ArgumentParser(description="""Convert a htpasswd file to a format that can be
    imported to RestAuth""" )
parser.add_argument('--plain', default=False, action='store_true',
    help="Interpret lines with unknown algorithm as plain-text instead of crypt" )
parser.add_argument('file', help="A file to parse")

args = parser.parse_args()

input = open(args.file, 'r')
for line in input.readlines():
    user, hash = line.split(':')
    hash = hash.strip()
    
    if hash.startswith( "$apr1$" ): # md5 hash
        none, salt, hash = hash.rsplit('$', 2)
        data[user] = {'password': { 'algorithm': 'apr1', 'salt': salt, 'hash': hash }}
        # see test.php
    elif hash.startswith( "{SHA}"): # sha with no hash (according to man-page)
        hash = binascii.b2a_hex(base64.b64decode(bytes(hash[5:], 'utf-8')))
        data[user] = {'password': { 'algorithm': 'sha1', 'hash': hash.decode('utf-8') }}
    else: # either crypt or plain
        if args.plain:
# TODO: encrypt passwords as sha512
            data[user] = {}
        else: # crypt
            salt, hash = hash[0:2], hash[2:]
            data[user] = {'password': { 'algorithm': 'crypt', 'salt': salt, 'hash': hash }}
    
print( json.dumps( {'users': data}))
# cleanup:
input.close()