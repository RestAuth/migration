#!/usr/bin/python3

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