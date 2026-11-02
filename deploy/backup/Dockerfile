# Integral del Este — copias de seguridad: pg_dump cifrado con restic a un bucket S3 externo.
FROM postgres:17
RUN apt-get update \
 && apt-get install -y --no-install-recommends restic curl ca-certificates tzdata \
 && rm -rf /var/lib/apt/lists/*
COPY backup.sh /backup.sh
COPY loop.sh /loop.sh
RUN chmod +x /backup.sh /loop.sh
ENTRYPOINT ["/loop.sh"]
