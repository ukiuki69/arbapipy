#!/usr/bin/python3.6
# -- coding: utf-8 --
import io
import sys
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
print("Content-Type: text/html; charset=UTF-8;\n\n")
print('this is albatross-sub-processing server.')
