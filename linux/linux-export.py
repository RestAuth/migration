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

import os, sys, grp, pwd, spwd, json, argparse

data = {}

parser = argparse.ArgumentParser(description="""Extract linux system accounts and convert them into
    the RestAuth import data format.""" )
parser.add_argument('--users', default=False, action='store_true', help="Add users.")
parser.add_argument('--props', default=False, action='store_true',
    help="Add the users home directory and login shell as properties.")
parser.add_argument('--groups', default=False, action='store_true', help="Add user groups.")
parser.add_argument('--system-users', default=False, action='store_true',
    help="Also add system users.")
parser.add_argument('--system-groups', default=False, action='store_true',
    help="Also add system groups.")
parser.add_argument('--service', help="Set groups to belong to SERVICE.")
args = parser.parse_args()

# parse config-file:
f = open('/etc/adduser.conf')
lines = f.readlines()
FIRST_UID = int([l.split('=')[1].strip() for l in lines if l.startswith('FIRST_UID')][0])
LAST_UID = int([l.split('=')[1].strip() for l in lines if l.startswith('LAST_UID')][0])
FIRST_SYSTEM_UID = int([l.split('=')[1].strip() for l in lines if l.startswith('FIRST_SYSTEM_UID')][0])
LAST_SYSTEM_UID = int([l.split('=')[1].strip() for l in lines if l.startswith('LAST_SYSTEM_UID')][0])
FIRST_GID = int([l.split('=')[1].strip() for l in lines if l.startswith('FIRST_GID')][0])
LAST_GID = int([l.split('=')[1].strip() for l in lines if l.startswith('LAST_GID')][0])
FIRST_SYSTEM_GID = int([l.split('=')[1].strip() for l in lines if l.startswith('FIRST_SYSTEM_GID')][0])
LAST_SYSTEM_GID = int([l.split('=')[1].strip() for l in lines if l.startswith('LAST_SYSTEM_GID')][0])
f.close()

groups = {}

def is_user(id):
    if id >= FIRST_UID and id <= LAST_UID:
        return True
    else:
        return False
def is_system_user(id):
    if id >= FIRST_SYSTEM_UID and id <= LAST_SYSTEM_UID:
        return True
    else:
        return False

# parse users:
if args.users:
    users = {}
    for user in pwd.getpwall():
        id = user.pw_uid
        if is_system_user(id) and not args.system_users:
            continue
        if not is_user(id) and not is_system_user(id):
            continue
        
        users[user.pw_name] = {}
        
        # set password
        if user.pw_passwd == 'x': # check /etc/shadow
            print( user.pw_name)
            shadow = spwd.getspnam(user.pw_name)
            shadow_pwd = shadow.sp_pwd
            if len(shadow_pwd) > 1:
                salt, hash = shadow_pwd.rsplit('$', 1)
                users[user.pw_name]['password'] = {'algorithm': 'crypt', 'salt': salt, 'hash': hash}
        elif len(user.pw_passwd) > 1:
            salt, hash = user.pw_passwd.rsplit('$', 1)
            users[user.pw_name]['password'] = {'algorithm': 'crypt', 'salt': salt, 'hash': hash}
            
        # add properties:
        if args.props:
            users[user.pw_name]['properties'] = {'shell': user.pw_shell, 'home': user.pw_dir}
            
        # if groups, add main group:
        if args.groups:
            group = grp.getgrgid(user.pw_gid)
            if group.gr_name in groups:
                groups[group.gr_name]['users'].append(user.pw_name)
            else:
                groups[group.gr_name] = {'users': [user.pw_name]}
                if args.service:
                    groups[group.gr_name]['service'] = args.service
            
    data['users'] = users

def is_group(id):
    if id >= FIRST_GID and id <= LAST_GID:
        return True
    else:
        return False
def is_system_group(id):
    if id >= FIRST_SYSTEM_GID and id <= LAST_SYSTEM_GID:
        return True
    else:
        return False

if args.groups:
    usernames = data['users'].keys()
    for group in grp.getgrall():
        id = group.gr_gid
        if is_system_group(id) and not args.system_groups:
            continue
        if not is_group(id) and not is_system_group(id):
            continue
        
        # skip system groups:
        
        # Add groups (may have been added before as primary user group):  
        if group.gr_name not in groups:
            groups[group.gr_name] = {'users': []}
            if args.service:
                groups[group.gr_name]['service'] = args.service
        
        # Add members:
        for member in group.gr_mem:
            if member in usernames:
                groups[group.gr_name]['users'].append(member)
    
    data['groups'] = groups
    
if args.service:
    data['services'] = {args.service: {}}

print(json.dumps(data, indent=4))