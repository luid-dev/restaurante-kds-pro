# syntax=docker/dockerfile:1

# SPA React compilada con pnpm y servida por Nginx (que además enruta /api y /app).
FROM node:22-alpine AS build

RUN npm install --global pnpm@10.17.1

WORKDIR /app
COPY frontend/package.json frontend/pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY frontend/ ./

ARG VITE_APP_NAME="KDS Pro"
ARG VITE_API_BASE_URL=/api/v1
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_PORT=8088
ARG VITE_REVERB_SCHEME=http
ARG VITE_DEMO_MODE=false
ENV VITE_APP_NAME=$VITE_APP_NAME \
    VITE_API_BASE_URL=$VITE_API_BASE_URL \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME \
    VITE_DEMO_MODE=$VITE_DEMO_MODE

RUN pnpm run build

FROM nginx:1.27-alpine
COPY docker/frontend/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 8080
