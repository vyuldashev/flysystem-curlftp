FROM stilliard/pure-ftpd
MAINTAINER RubtsovAV@gmail.com

ENV FTP_USER ftpuser
ENV FTP_PASS password
ENV FTP_PATH /home/ftpusers/ftpuser
ENV FTP_PUBLICHOST 127.0.0.1

CMD echo "$FTP_PASS\n$FTP_PASS" | pure-pw useradd $FTP_USER -u ftpuser -d $FTP_PATH && pure-pw mkdb && /usr/sbin/pure-ftpd -c 50 -C 10 -l puredb:/etc/pure-ftpd/pureftpd.pdb -E -j -R -P $FTP_PUBLICHOST -p 30000:30009