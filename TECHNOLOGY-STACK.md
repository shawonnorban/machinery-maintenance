# Annotech RMG SaaS

## Technology Stack

This document records the initial technology decisions for a paperless Ready-Made Garments (RMG) production management SaaS. The platform will support multiple garment companies and their teams across the complete order-to-production lifecycle:

> Buyer order -> Merchandising -> Planning -> Cutting -> Sewing -> Washing/Dyeing -> Finishing -> Quality -> Packing -> Shipment -> Reports

These decisions are a starting point and can be revised through an architecture decision record when the product grows.

## Web Application

| Area | Decision |
|---|---|
| Backend API | Laravel 13 |
| Frontend | Next.js 16 with React 19 and JavaScript |
| Database | PostgreSQL |
| Cache and temporary data | Redis |
| Background jobs | Laravel Queues backed by Redis |
| Styling | Tailwind CSS |
| UI components | Shadcn/ui and HeroUI, used selectively and consistently |
| Icons | Lucide React and React Icons |
| API authentication | Laravel Sanctum |
| Roles and permissions | Spatie Laravel Permission |
| API contract | Versioned REST API; OpenAPI documentation will be added as the API stabilizes |
| Validation and testing | Laravel Pest/PHPUnit, React testing as needed, and end-to-end browser tests for critical workflows |

### Frontend and backend boundary

Laravel will own business rules, authorization, workflows, database access, queues, notifications, and reporting APIs. Next.js will own the web user experience and will consume the Laravel API. Business rules should not be duplicated in the frontend.

## SaaS and Multi-Tenancy

The system must isolate each garment company (tenant) from every other company.

Initial recommendation:

- Use a shared PostgreSQL database with a `tenant_id` on tenant-owned tables.
- Enforce tenant scoping in application services, policies, queries, and jobs.
- Add automated tests proving that users cannot read or modify another tenant's data.
- Keep tenant-specific settings, users, factories, buyers, suppliers, styles, orders, and production records separate.
- Reconsider separate databases or schemas per tenant only when compliance, scale, or customer requirements justify the operational cost.

Tenant isolation is a core security requirement, not only a filtering feature.

## Realtime Notifications

- Laravel Reverb for the realtime server.
- Laravel Echo on the Next.js frontend.
- Redis for queue and event support where appropriate.
- Use realtime events for production status changes, approvals, alerts, and operational notifications.
- Persist important notifications in PostgreSQL so users can review them when offline.

## Mobile Application

Mobile is deferred until the web workflows and API contracts are stable.

The preferred future option is React Native with JavaScript, sharing API clients, validation schemas, and design tokens with the Next.js application where practical. UI components will be shared only when that improves maintainability; mobile and web should not be forced into identical interfaces.

## Infrastructure and Deployment

### Initial hosting

- DigitalOcean VPS for the first production deployment.
- DigitalOcean Spaces for documents, images, attachments, and generated files.
- S3-compatible object storage access through Laravel's filesystem abstraction.
- Managed PostgreSQL and managed Redis are preferred when the budget and region availability allow them.
- HTTPS with automated certificate renewal.
- Automated backups for PostgreSQL and object storage.

### Containers and scaling

- **Decision: Use Docker from the beginning.** It will standardize local development, testing, and deployment across the team.
- Docker for application containers and repeatable deployments.
- Docker Compose for the local development environment, including Laravel, Next.js, PostgreSQL, Redis, Reverb, and queue workers.
- CI/CD pipeline for tests, builds, migrations, and deployments.
- Kubernetes is a future scaling option, not an initial requirement. Adopt it when traffic, availability requirements, or operational needs justify the added complexity.
- Stateless Laravel and Next.js application containers so horizontal scaling and load balancing remain possible.
- Separate workers for queues and scheduled tasks.

Docker makes the application portable, but moving to Kubernetes is not automatic. A future Kubernetes deployment will also need container registries, Kubernetes Deployments and Services, readiness and liveness checks, an Ingress or cloud load balancer, secrets and configuration management, autoscaling rules, persistent storage strategy, and CI/CD deployment automation. PostgreSQL, Redis, and object storage should remain managed or external so application containers can scale safely.

## Supporting Services To Plan For

- Laravel Scheduler for recurring production and reporting tasks.
- Laravel Horizon for Redis queue monitoring.
- Centralized application logging and error tracking.
- Monitoring for uptime, CPU, memory, database health, queue health, storage, and realtime connections.
- Email provider for invitations, approvals, alerts, and account recovery.
- Optional SMS or WhatsApp provider for future factory-floor notifications.

## Security Baseline

- Tenant-aware authorization on every API endpoint.
- Least-privilege roles and permissions.
- Audit logs for important changes such as order approvals, quantity changes, status changes, and shipment updates.
- Server-side validation for all input and controlled file uploads.
- Rate limiting, secure headers, CSRF protection where applicable, and secure cookie/token handling.
- Encryption in transit and encrypted backups.
- No production secrets committed to the repository.

## Recommended Delivery Order

1. Laravel API foundation, authentication, tenants, users, roles, permissions, and audit logging.
2. Buyer, style, order, purchase order, and merchandising modules.
3. Production planning, cutting, sewing, washing/dyeing, finishing, quality, packing, and shipment modules.
4. Documents, approvals, reports, dashboards, queues, and notifications.
5. Realtime updates with Reverb and Echo.
6. Mobile application after the API and web workflows are proven in production.
7. Kubernetes and advanced infrastructure only when actual usage requires them.

## Decision Summary

The proposed stack is suitable for a multi-tenant RMG SaaS. The most important architectural decisions are strict tenant isolation, a clear Laravel API/Next.js boundary, durable document storage, background jobs, auditability, and a deployment path that starts simply on DigitalOcean while allowing later horizontal scaling.

The main recommendation is to start with Docker and a well-structured VPS deployment, while treating Kubernetes as a later phase rather than a day-one dependency.

