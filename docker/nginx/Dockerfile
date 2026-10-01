FROM node:20-alpine AS node-build

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/ resources/
COPY vite.config.js ./
RUN npm run build

FROM nginx:alpine
WORKDIR /var/www
COPY . /var/www
COPY --from=node-build /app/public/build /var/www/public/build
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
