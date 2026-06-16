@echo off
cd /d "c:\xampp\htdocs\Koopjeskoken\scraper"
"C:\Program Files\nodejs\node.exe" src\index.js
if errorlevel 1 (
  echo Scraper failed, skipping upload.
  exit /b 1
)
"C:\Program Files (x86)\WinSCP\WinSCP.com" /script="c:\xampp\htdocs\Koopjeskoken\scraper\upload.txt" /log="c:\xampp\htdocs\Koopjeskoken\scraper\upload.log"
