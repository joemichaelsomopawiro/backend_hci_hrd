$client = New-Object System.Net.WebClient
try {
    $content = $client.DownloadString("http://localhost/backend_hci/public/test-notification")
    Write-Output $content
} catch {
    Write-Output "Error: $($_.Exception.Message)"
}
