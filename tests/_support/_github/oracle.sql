alter session set "_ORACLE_SCRIPT"=true;
CREATE USER test IDENTIFIED BY "Oracle18" QUOTA 50M ON system;
GRANT DBA TO test;