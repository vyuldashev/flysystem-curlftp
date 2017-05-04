#/bin/sh

docker_compose()
{
	docker-compose -p flysystem-curlftp/tests -f tests/docker/docker-compose.yml $@
}

# creating directory for resources
if test ! -d "tests/resources"; then
	mkdir -m=0777 "tests/resources"
	if test $? != 0; then exit 1; fi
fi

echo
echo "-----------"
echo "Test ftp adapter with vsftpd"
echo "Launching the vsftpd on port 221"
docker_compose up -d vsftpd
docker_compose run wait vsftpd:21 -t 30
FTP_ADAPTER_PORT=221 vendor/bin/phpunit
# remember the exit code of last command
rc=$?	
# stopping containers
docker_compose down
# exit if phpunit did not return 0
if test $rc != 0; then exit $rc; fi

echo
echo "-----------"
echo "Test ftp adapter with pure-ftpd"
echo "Launching the pure-ftpd on port 222"
docker_compose up -d pure-ftpd
docker_compose run wait pure-ftpd:21 -t 30
FTP_ADAPTER_PORT=222 vendor/bin/phpunit
# remember the exit code of last command
rc=$?	
# stopping containers
docker_compose down
# exit if phpunit did not return 0
if test $rc != 0; then exit $rc; fi

echo
echo "-----------"
echo "Test completed"
