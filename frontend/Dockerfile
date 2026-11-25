# Cliente React del ICAP (capa 1) para ejecutar todo con Docker en desarrollo.
# Se usa desde la raíz:  docker compose --profile app up -d --build
FROM node:22-slim

WORKDIR /app

# Dependencias primero: esta capa solo se reconstruye si cambia package.json
COPY package.json package-lock.json ./
RUN npm ci

COPY . .

EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
