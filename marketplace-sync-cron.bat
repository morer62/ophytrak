@echo off
cd /d %~dp0
if not exist .logs mkdir .logs
php bin\command.php marketplace-sync >> .logs\marketplace-sync.log 2>&1
