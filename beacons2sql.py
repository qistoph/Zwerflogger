#!/usr/bin/python

import sys

print "INSERT INTO beacons (beaconid, tag, score) VALUES"

n=0
for line in sys.stdin:
    if n > 0:
        print ","
    print "(lower(hex(randomblob(16))), '%s', 1)" % line.rstrip()
    n += 1

print ";"
