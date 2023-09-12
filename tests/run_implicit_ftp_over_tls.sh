#!/bin/sh
#
# testing implicit FTP over SSL

docker_compose()
{
	docker-compose -p flysystem-curlftp-tests -f tests/docker/docker-compose.yml $@
}

error_test()
{
	docker_compose down
	exit "$1"
}

# creating directory for resources
if test ! -d "tests/resources"; then
	mkdir -m 0777 "tests/resources"
	if test $? != 0; then exit 1; fi
fi

# regenerate ckeys
openssl req -new -newkey rsa:2048 -days 365 -nodes -sha256 -x509 -keyout "tests/docker/vsftpd-implicit-ssl/vsftpd.key" -out "tests/docker/vsftpd-implicit-ssl/vsftpd.crt" -subj '/CN=self_signed'

# ----- vsftpd-implicit-ssl
echo ""
echo "-----------"
echo "Test ftp adapter with vsftpd with implicit FTP over SSL"
echo "Launching the vsftpd on port 990"
docker_compose up -d vsftpd-implicit-ssl
docker_compose run wait vsftpd-implicit-ssl:990 -t 30

echo ""
echo "Test vsftpd-implicit-ssl with root=/chroot"
XDEBUG_MODE=coverage FTP_ADAPTER_PORT=990 FTP_ADAPTER_ROOT=/chroot FTP_ADAPTER_FTPS=1 vendor/bin/phpunit
# remember the exit code of last command
rc=$?
# exit if phpunit did not return 0
if test $rc != 0; then
	echo "Tests failed, with return code : ${rc}"
	error_test $rc;
fi

# stopping containers
docker_compose down

echo
echo "-----------"
echo "Test completed"
