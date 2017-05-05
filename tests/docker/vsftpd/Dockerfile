FROM fauria/vsftpd
MAINTAINER RubtsovAV@gmail.com

RUN echo "file_open_mode=0777" >> /etc/vsftpd/vsftpd.conf \
	&& echo "local_umask=0000" >> /etc/vsftpd/vsftpd.conf
