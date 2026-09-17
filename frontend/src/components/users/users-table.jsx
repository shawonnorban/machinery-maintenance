"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { Search } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { Card, CardBody } from "@/components/ui/card";
import { useT } from "@/lib/i18n";

function UsersTable({ users, meta, page, search, status }) {
  const t = useT("user");
  const tc = useT("common");
  const STATUS_OPTIONS = [
    { value: "", label: t("all_statuses") },
    { value: "ACTIVE", label: t("active") },
    { value: "SUSPENDED", label: t("suspended") },
  ];
  const router = useRouter();
  const [searchInput, setSearchInput] = useState(search);

  useEffect(() => {
    if (searchInput === search) {
      return undefined;
    }
    const timeout = setTimeout(() => navigate({ search: searchInput, page: 1 }), 350);
    return () => clearTimeout(timeout);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput]);

  function navigate(next) {
    const params = new URLSearchParams({
      page: String(next.page ?? page),
      search: next.search ?? search,
      status: next.status ?? status,
    });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/settings/users?${params.toString()}`);
  }

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardBody className="flex flex-wrap items-center gap-3 py-3">
          <div className="relative w-full flex-1 min-w-[200px]">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder={t("search")}
              className="pl-9"
            />
          </div>
          <div className="w-full max-w-[200px]">
            <Select options={STATUS_OPTIONS} value={status} onValueChange={(value) => navigate({ status: value, page: 1 })} />
          </div>
        </CardBody>
      </Card>

      <DataTable
        columns={[
          {
            key: "name",
            header: t("name"),
            render: (user) => (
              <div>
                <Link href={`/settings/users/${user.id}`} className="font-medium text-brand hover:underline">
                  {user.name}
                </Link>
                <div className="text-xs text-foreground-muted">{user.email}</div>
              </div>
            ),
          },
          {
            key: "roles",
            header: t("roles"),
            render: (user) => (
              <div className="flex flex-wrap gap-1">
                {user.roles.length > 0 ? user.roles.map((role) => <Badge key={role} variant="neutral">{role}</Badge>) : "—"}
              </div>
            ),
          },
          {
            key: "status",
            header: t("status"),
            render: (user) => <StatusBadge status={user.status} label={user.status === "ACTIVE" ? t("active") : t("suspended")} />,
          },
        ]}
        rows={users}
        rowKey={(user) => user.id}
        emptyTitle={t("no_users_found")}
        rowActions={(user) => [{ label: tc("view"), onSelect: () => router.push(`/settings/users/${user.id}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { UsersTable };
