# syntax=docker/dockerfile:1
# machinery-maintenance — Next.js development runtime
FROM node:20-alpine

RUN apk add --no-cache bash curl libc6-compat

WORKDIR /app

# Dependencies are installed into an image layer; the anonymous volume on
# /app/node_modules in docker-compose keeps them out of the Windows bind mount.
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm install

EXPOSE 3000
# -H 0.0.0.0: the container's loopback isn't the host's — without this the
# dev server only accepts connections from inside its own network namespace.
CMD ["npm", "run", "dev", "--", "-H", "0.0.0.0"]
