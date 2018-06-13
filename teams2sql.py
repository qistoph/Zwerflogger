#!/usr/bin/python

import sys

print "INSERT INTO teams (teamid, name) VALUES"

n=0
for line in sys.stdin:
    if n > 0:
        print ","
    print "(lower(hex(randomblob(16))), '%s')" % line.rstrip()
    n += 1

print ";"
