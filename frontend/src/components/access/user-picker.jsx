"use client";

import { useRouter } from "next/navigation";
import { Select } from "@/components/ui/select";

/** User Account Registration → User Roles: "-- Select user --", which opens that user's roles. */
function UserPicker({ users, value, placeholder }) {
  const router = useRouter();

  return (
    <div className="w-full max-w-md">
      <Select value={value} onValueChange={(id) => router.push(`/access-control/user-account?tab=roles&user=${id}`)} placeholder={placeholder} options={users.map((u) => ({ value: u.id, label: u.label }))} />
    </div>
  );
}

export { UserPicker };
