@echo off
setlocal EnableDelayedExpansion

REM ==========================================================================
REM  Souriane - demarrage local (Apache + MySQL) et acces depuis le reseau
REM ==========================================================================
REM  Double-cliquer sur ce fichier pour :
REM    1. demarrer MySQL et Apache s'ils ne tournent pas deja ;
REM    2. attendre que le site reponde vraiment, par sondage HTTP 200 ;
REM    3. afficher l'adresse a ouvrir depuis un telephone sur le meme Wi-Fi.
REM
REM  Le site est servi depuis C:\xampp\htdocs\sourianemedia, qui pointe vers le
REM  dossier de travail. Apache ecoute sur toutes les interfaces du poste.
REM
REM  Aucun delai fixe : on sonde jusqu'a obtenir une vraie reponse, sinon un
REM  demarrage a froid est declare en echec a tort.
REM ==========================================================================

set "XAMPP=C:\xampp"
set "SOUS_DOSSIER=sourianemedia"
set "URL_LOCALE=http://localhost/%SOUS_DOSSIER%/"
set "PS=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"
set "LOG=%~dp0demarrer-local.log"

title Souriane - demarrage local

> "%LOG%" echo === Souriane - demarrage local du %DATE% a %TIME% ===

echo.
echo   ================================================
echo    SOURIANE - demarrage local
echo   ================================================
echo.

REM --- 1/4 : MySQL, port 3306 ------------------------------------------------
set "MYSQL_UP=0"
"%PS%" -NoProfile -ExecutionPolicy Bypass -Command "try{ $t=New-Object Net.Sockets.TcpClient; $t.Connect('127.0.0.1',3306); $c=$t.Connected; $t.Close(); if($c){ exit 0 } else { exit 1 } }catch{ exit 1 }"
if not errorlevel 1 set "MYSQL_UP=1"
if "!MYSQL_UP!"=="1" echo   [1/4] MySQL repond deja sur 3306 : reutilise.
if "!MYSQL_UP!"=="0" echo   [1/4] MySQL en cours de demarrage...
if "!MYSQL_UP!"=="0" start "Souriane - MySQL" /min /D "%XAMPP%" "%XAMPP%\mysql_start.bat"
>> "%LOG%" echo [1/4] MySQL_UP=!MYSQL_UP!

REM --- 2/4 : Apache, port 80 -------------------------------------------------
set "APACHE_UP=0"
"%PS%" -NoProfile -ExecutionPolicy Bypass -Command "try{ $t=New-Object Net.Sockets.TcpClient; $t.Connect('127.0.0.1',80); $c=$t.Connected; $t.Close(); if($c){ exit 0 } else { exit 1 } }catch{ exit 1 }"
if not errorlevel 1 set "APACHE_UP=1"
if "!APACHE_UP!"=="1" echo   [2/4] Apache repond deja sur 80 : reutilise.
if "!APACHE_UP!"=="0" echo   [2/4] Apache en cours de demarrage...
if "!APACHE_UP!"=="0" start "Souriane - Apache" /min /D "%XAMPP%" "%XAMPP%\apache_start.bat"
>> "%LOG%" echo [2/4] Apache_UP=!APACHE_UP!

REM --- 3/4 : attendre une vraie reponse HTTP 200 -----------------------------
echo   [3/4] Attente du site, sondage HTTP sans delai fixe...
"%PS%" -NoProfile -ExecutionPolicy Bypass -Command "$ProgressPreference='SilentlyContinue'; $ErrorActionPreference='SilentlyContinue'; $a=0; for($i=0;$i -lt 40;$i++){ try{ $a=(Invoke-WebRequest -UseBasicParsing -Uri '%URL_LOCALE%' -TimeoutSec 3).StatusCode }catch{ $a=0 }; if($a -eq 200){ exit 0 }; Start-Sleep -Milliseconds 1500 }; exit 1"
if errorlevel 1 goto echec
echo   [3/4] Le site repond : HTTP 200.
>> "%LOG%" echo [3/4] HTTP 200 sur %URL_LOCALE%

REM --- 4/4 : adresse reseau ------------------------------------------------
set "IP_LAN="
"%PS%" -NoProfile -ExecutionPolicy Bypass -Command "$c=Get-NetIPConfiguration; $ip=''; foreach($x in $c){ if($ip -eq '' -and $x.IPv4DefaultGateway -ne $null){ $ip=$x.IPv4Address.IPAddress } }; Set-Content -Path '%TEMP%\souriane-ip.txt' -Value $ip -Encoding ascii"
if exist "%TEMP%\souriane-ip.txt" set /p IP_LAN=<"%TEMP%\souriane-ip.txt"
if "!IP_LAN!"=="" set "IP_LAN=introuvable"
>> "%LOG%" echo [4/4] IP_LAN=!IP_LAN!

set "URL_TEL=http://!IP_LAN!/%SOUS_DOSSIER%/"

echo   [4/4] Adresses :
echo.
echo         Sur cet ordinateur : %URL_LOCALE%
echo         Sur le telephone   : !URL_TEL!
echo.
echo   Le telephone doit etre sur le MEME reseau Wi-Fi.
echo   En HTTP, sans https, les notifications push ne fonctionnent pas :
echo   c'est une regle du navigateur, pas un defaut de l'application.
echo.

if not "!IP_LAN!"=="introuvable" start "" "!URL_TEL!"

echo   ================================================
echo    Pret. Fermer cette fenetre n'arrete pas les serveurs.
echo    Pour tout arreter : xampp_stop.exe, ou apache_stop.bat + mysql_stop.bat
echo    Journal de ce lanceur : %LOG%
echo   ================================================
echo.
pause
exit /b 0

:echec
echo.
echo   [ECHEC] Le site n'a pas repondu HTTP 200 dans le delai imparti.
echo.
echo   A verifier, dans cet ordre :
echo     1. le journal de ce lanceur : %LOG%
echo     2. le journal MySQL : %XAMPP%\mysql\data\mysql_error.log
echo     3. le port 80 est peut-etre occupe par un autre programme
echo     4. relancer depuis une invite de commandes ouverte, pour lire le detail
echo.
pause
exit /b 1
