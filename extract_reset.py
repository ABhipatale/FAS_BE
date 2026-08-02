import io, re, urllib.parse
s = io.open('storage/logs/laravel.log', encoding='utf-8', errors='ignore').read()
matches = re.findall(r'https?://[^\s"<>\]]*reset-password[^\s"<>\]]*', s)
url = matches[-1].replace('&amp;', '&').rstrip('\')
print('URL:', url[:150])
q = urllib.parse.parse_qs(urllib.parse.urlparse(url).query)
io.open('.claude_token.tmp', 'w').write(q['token'][0])
print('token length:', len(q['token'][0]), '| email:', q['email'][0])
