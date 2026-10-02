FROM postgres:16-alpine
RUN apk add --no-cache tar gzip openssl tzdata \
 && addgroup -g 10001 backup && adduser -D -u 10001 -G backup backup \
 && mkdir -p /backups && chown backup:backup /backups
ENV TZ=Europe/Berlin
COPY docker/backup/backup.sh /usr/local/bin/backup
COPY docker/backup/restore.sh /usr/local/bin/restore
RUN chmod 0555 /usr/local/bin/backup /usr/local/bin/restore
USER backup
ENTRYPOINT ["/usr/local/bin/backup"]
CMD ["loop"]
