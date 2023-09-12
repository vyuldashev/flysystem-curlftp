FROM panubo/vsftpd

RUN echo "file_open_mode=0666" >> /etc/vsftpd_ssl.conf \
    && echo "local_umask=066" >> /etc/vsftpd_ssl.conf \
    && echo "implicit_ssl=YES" >> /etc/vsftpd_ssl.conf \
    && echo "chmod_enable=YES" >> /etc/vsftpd_ssl.conf \
    && echo "listen_port=990" >> /etc/vsftpd_ssl.conf

ENTRYPOINT ["/entry.sh"]
CMD ["vsftpd" , "/etc/vsftpd_ssl.conf"]
