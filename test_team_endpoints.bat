@echo off
echo === Testing Team Members Endpoint ===
echo.

REM Get token first (you need to update this with your actual token)
set TOKEN=YOUR_TOKEN_HERE

echo Testing GET team members...
curl -X GET "http://127.0.0.1:8000/api/program-regular/manager-program/programs/6/team-members" ^
-H "Accept: application/json" ^
-H "Authorization: Bearer %TOKEN%"

echo.
echo.
echo === Testing POST team member ===
echo.

curl -X POST "http://127.0.0.1:8000/api/program-regular/manager-program/programs/6/team-members" ^
-H "Accept: application/json" ^
-H "Content-Type: application/json" ^
-H "Authorization: Bearer %TOKEN%" ^
-d "{\"user_id\":2,\"role\":\"Creative\"}"

pause
