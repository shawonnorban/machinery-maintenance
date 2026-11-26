import {
  ArrowRightLeft,
  BadgeCheck,
  CheckCheck,
  CircleCheck,
  CirclePlay,
  CircleX,
  ClipboardPlus,
  FilePen,
  Lock,
  MapPin,
  PackageCheck,
  Pause,
  Pencil,
  Play,
  Power,
  PowerOff,
  Printer,
  RotateCcw,
  Send,
  ThumbsDown,
  ThumbsUp,
  Trash2,
  Truck,
  Upload,
  UserPlus,
  Wrench,
} from "lucide-react";

/**
 * The icon a header/action button carries, by what it does — one set for
 * every Machinery detail page so "Hold" or "Approve" looks the same on a work
 * order, a breakdown and a transfer, the way the ERP's other modules show an
 * icon on each action button.
 */
const ICONS = {
  edit: Pencil,
  delete: Trash2,
  activate: Power,
  deactivate: PowerOff,
  submitForApproval: Send,
  start: Play,
  startWorkOrder: Play,
  resume: CirclePlay,
  resumeProduction: CirclePlay,
  hold: Pause,
  complete: CircleCheck,
  completeRepair: CircleCheck,
  verify: BadgeCheck,
  close: Lock,
  cancel: CircleX,
  reopen: RotateCcw,
  reopenRepair: RotateCcw,
  acknowledge: CheckCheck,
  arrive: MapPin,
  assign: UserPlus,
  raiseWorkOrder: ClipboardPlus,
  approve: ThumbsUp,
  reject: ThumbsDown,
  dispatch: Truck,
  receive: PackageCheck,
  transfer: ArrowRightLeft,
  publish: Upload,
  revise: FilePen,
  print: Printer,
  repair: Wrench,
};

/** `<ActionIcon name="hold" />` — nothing for a name without an icon, so a caller can pass any action key. */
function ActionIcon({ name, className }) {
  const Icon = ICONS[name];
  return Icon ? <Icon className={className} aria-hidden="true" /> : null;
}

export { ActionIcon };
