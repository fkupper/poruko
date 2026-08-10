# Poruko Kubernetes (alpha)

Thin manifests that reuse the same single GHCR image as Docker Compose (`ghcr.io/fkupper/poruko`). **Bring your own Postgres and Redis** — these charts do not install databases.

## Apply

1. Edit [configmap.yaml](configmap.yaml): `APP_URL`, `DB_HOST`, `REDIS_HOST`, etc.
2. Create the Secret (do not commit real values):

   ```bash
   # Option A: from env file
   kubectl create namespace poruko
   kubectl -n poruko create secret generic poruko-secret --from-env-file=secret.env

   # Option B: copy secret.example.yaml → secret.yaml, edit, then:
   # kubectl apply -f deploy/k8s/secret.yaml
   ```

3. Pin image tags in `kustomization.yaml` (`newTag: 0.1.0`).
4. Adjust [ingress.yaml](ingress.yaml) for your ingress controller and hostname.

```bash
kubectl apply -k deploy/k8s
```
The `api` Deployment runs migrations on start (`RUN_MIGRATIONS=true`). Queue, scheduler, and web use the same image with different args (`PORUKO_ROLE=web` + nginx for the frontend).

The web nginx config proxies `/api` to hostname `api` on port `8000` (the Service name in this folder). Keep that Service name or rebuild the web image with a different upstream.

## Notes

- Single replica for scheduler — do not scale it horizontally without a distributed lock.
- For local all-in-one trials, prefer Compose with `COMPOSE_PROFILES=bundle` instead of in-cluster Postgres operators.
