"use client";

import { useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";

/**
 * "User must change password at next login" (AnnotechERP 02/01): until the
 * password is changed, every screen sends the person to Your Account. The API
 * still answers normally; this only stops them wandering off the one screen
 * they have to use first.
 */
function ForcePasswordChange({ required }) {
  const pathname = usePathname();
  const router = useRouter();

  useEffect(() => {
    if (required && pathname !== "/account") router.replace("/account");
  }, [required, pathname, router]);

  return null;
}

export { ForcePasswordChange };
