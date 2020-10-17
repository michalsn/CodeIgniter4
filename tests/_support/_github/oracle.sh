#!/bin/bash

source /home/oracle/.bashrc

echo "Setting Up Test Database"
sqlplus sys/Oracle18@localhost/XE as sysdba < /tests/_support/_github/oracle.sql

echo "Oracle Setup Complete"
