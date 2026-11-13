@echo off
REM Esta prueba se ejecuta desde CMD. El resumen se genera SOLO en lunes.
REM Para probar en otro dia, entra como dueno y pulsa "Generar resumen para la demo".
set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" (
  echo No se encontro PHP en C:\xampp\php\php.exe
  echo Comprueba la ruta de tu instalacion de XAMPP.
  pause
  exit /b 1
)
"%PHP%" "%~dp0resumen_cada_lunes.php"
pause
