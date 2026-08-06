ZAY POS System 2.0 - Starter Setup
==================================

1. Extract the contents into:
   C:\UniServerZ\www\zaypos2

2. Open PowerShell:
   cd C:\UniServerZ\www\zaypos2
   composer install

3. Create the environment file:
   Copy-Item .env.example .env

4. Open .env in Notepad and replace DB_PASSWORD with the real password
   for the zaypos_app MySQL user. Do not share or commit this file.

5. Open:
   http://localhost/zaypos2/public/

Expected result:
   PHP: 8.3.0
   Database: Connected
   ZAY POS 2.0 foundation is ready.

Security:
   - Never use the MySQL root account in .env.
   - Keep .env private.
   - Keep this server on the trusted shop LAN.
   - Delete the old public/health.php after this starter replaces the folder.
