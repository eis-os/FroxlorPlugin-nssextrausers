# Froxlor nssextrausers Plugin

Allow using nss-extrausers together with experimental froxlor plugin support.
See https://github.com/eis-os/Froxlor/tree/plugin-system-rework for plugin support

## Installation

###
1. Install libnss-extrausers (apt-get libnss-extrausers)
2. Put nssextrausers into Froxlor plugins directory
3. Eable plugin and configure
4. Run froxlor cronjob
5. Check /var/lib/extrausers/ for proper configuration files
6. Change /etc/nsswitch.conf as seen in debian configuration for nssextrauserss
7. Restart all services or reboot so nss will use extrausers.

## License

May be found in COPYING

## FAQ

#### After enabling fcgid, newly created users show a permission problem
Check your webserver user-name setting in froxlor, then rewrite config.

#### Apache or other service is ignoring extrausers
Restart service fully, a configuration reload won't load extrausers

#### Shell login not possible by changing ftp_users (as with libnss-mysql)
TABLE_FTP_USERS is not used, shell is always set to /bin/false and password is disabled.

#### Other Distribution (untested)
Grab the source and compile/install
http://anonscm.debian.org/cgit/users/brlink/libnss-extrausers.git/
