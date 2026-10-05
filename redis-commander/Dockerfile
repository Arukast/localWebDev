# Built locally because the Docker Hub image is amd64-only and the VPS runs arm64.
FROM node:22-alpine
RUN npm install -g redis-commander
EXPOSE 8081
CMD ["redis-commander"]
