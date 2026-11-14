python3 << 'EOF'
# Photowall: one-time set-up so this server can get the app from GitHub. After this, one command installs and updates it:  pw-deploy
import os, subprocess, shutil
K = '/root/.ssh/photowall_deploy'; CFG = '/root/.ssh/config'; KH = '/root/.ssh/known_hosts'
REPO = 'git@github-photowall:sanilts/photowall.rewardhours.com.git'; APP = '/var/www/photowall'
if os.geteuid() != 0: raise SystemExit('Please run  sudo -i  first, then paste this again.')
for tool, pkg in (('git', 'git'), ('ssh-keygen', 'openssh-client')):
    if not shutil.which(tool): subprocess.run(['apt-get', 'install', '-y', pkg], check=False)
os.makedirs('/root/.ssh', mode=0o700, exist_ok=True)
if not os.path.isfile(K): subprocess.run(['ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-C', 'photowall-server', '-f', K], check=True)
cur = open(CFG).read() if os.path.isfile(CFG) else ''
if 'Host github-photowall' not in cur:
    open(CFG, 'a').write(('\n' if cur and not cur.endswith('\n') else '') + 'Host github-photowall\n  HostName github.com\n  User git\n  IdentityFile ' + K + '\n  IdentitiesOnly yes\n')
os.chmod(CFG, 0o600)
GH = 'github.com ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl'   # GitHub's published key
if GH not in (open(KH).read() if os.path.isfile(KH) else ''): open(KH, 'a').write(GH + '\n')
CMD = '''#!/bin/bash
# Photowall: first run fetches the app from GitHub; after that the app's own pw-deploy takes over.
APP=/var/www/photowall
if [ ! -d "$APP/.git" ]; then mkdir -p /var/www; git clone -q ''' + REPO + ''' "$APP" || { echo "Could not reach GitHub with the deploy key. Check that the key is added to the repository (Settings, Deploy keys)."; exit 1; }; fi
exec bash "$APP/deploy/pw-deploy" "$@"
'''
if not os.path.isfile('/usr/local/bin/pw-deploy'): open('/usr/local/bin/pw-deploy', 'w').write(CMD); os.chmod('/usr/local/bin/pw-deploy', 0o755)
try: ok = subprocess.run(['git', 'ls-remote', REPO], capture_output=True, text=True, timeout=60, env=dict(os.environ, GIT_SSH_COMMAND='ssh -o BatchMode=yes -o ConnectTimeout=20')).returncode == 0
except subprocess.TimeoutExpired: ok = False
if ok:
    print('Ready: the server can read the repository. Now run:  pw-deploy')
else:
    print('One more step: add this key to the repository on GitHub.')
    print('GitHub, sanilts/photowall.rewardhours.com, Settings, Deploy keys, Add deploy key. Title: photowall server. Leave "Allow write access" off.')
    print('Paste this line as the key:')
    print('')
    print(open(K + '.pub').read().strip())
    print('')
    print('Then run:  pw-deploy')
EOF
