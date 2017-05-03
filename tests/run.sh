#/bin/bash

# detect base dir
SOURCE="${BASH_SOURCE[0]}"
while [ -h "$SOURCE" ]; do # resolve $SOURCE until the file is no longer a symlink
  DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
  SOURCE="$(readlink "$SOURCE")"
  [[ $SOURCE != /* ]] && SOURCE="$DIR/$SOURCE" # if $SOURCE was a relative symlink, we need to resolve it relative to the path where the symlink file was located
done
DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

docker_compose()
{
	docker-compose -p flysystem-curlftp/tests -f $DIR/docker/docker-compose.yml $@
}

if [[ $1 == "build" ]]; then
	docker_compose build
	cd -
	exit
fi


echo "-----------"
echo "Launching containers"
docker_compose down

echo "Launching the vsftpd on port 221"
docker_compose up -d vsftpd
docker_compose run wait vsftpd:21 -t 30

echo "Launching the pure-ftpd on port 222"
docker_compose up -d pure-ftpd
docker_compose run wait pure-ftpd:21 -t 30

echo
echo "-----------"
echo "Test ftp adapter with vsftpd"
FTP_ADAPTER_PORT=221 vendor/bin/phpunit

echo
echo "-----------"
echo "Test ftp adapter with pure-ftpd"
FTP_ADAPTER_PORT=222 vendor/bin/phpunit

echo "-----------"
echo "Stop containers"
docker_compose down

echo
echo "-----------"
echo "Test completed"
