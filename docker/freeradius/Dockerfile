FROM freeradius/freeradius-server:latest

# Install MySQL client libraries and tools
RUN apt-get update && apt-get install -y \
    freeradius-mysql \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Create entrypoint script
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh && \
    sed -i 's/\r$//' /entrypoint.sh || true

ENTRYPOINT ["/entrypoint.sh"]
CMD ["freeradius", "-X"]
