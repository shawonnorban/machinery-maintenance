import { redirect } from "next/navigation";

/** Access Control opens on its first screen, as Brickland lists them. */
export default function AccessControlPage() {
  redirect("/access-control/module-registration");
}
