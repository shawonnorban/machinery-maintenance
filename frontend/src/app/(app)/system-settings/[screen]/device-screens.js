import { apiFetch } from "@/lib/api-server";
import { CommandQueue, DeviceRegister, EnrolledUsers, PunchLog } from "@/components/settings/devices";
import { deviceAction } from "./device-actions";

/*
 * Settings → Device Integration (AnnotechERP 03/01): Attendance Devices,
 * Device Punch Log, Device Enrolled Users and Device Command Queue. Each
 * screen draws its own tabs, switching in place.
 */

async function canManage() {
  const me = await apiFetch("/auth/permissions");
  return me.permissions.includes("settings.device.manage");
}

const screen = (load, Body) =>
  async function render() {
    const [data, manage] = await Promise.all([load(), canManage()]);
    return { tabs: [], body: async () => <Body {...data} canManage={manage} act={deviceAction} /> };
  };

const DEVICE_SCREENS = {
  "attendance-devices": screen(async () => ({ initial: await apiFetch("/settings/devices") }), DeviceRegister),
  "device-punch-log": screen(async () => {
    const [initial, options] = await Promise.all([apiFetch("/settings/device-punches"), apiFetch("/settings/devices/options")]);
    return { initial, options };
  }, PunchLog),
  "device-users": screen(async () => {
    const [initial, options] = await Promise.all([apiFetch("/settings/device-users"), apiFetch("/settings/devices/options")]);
    return { initial, options };
  }, EnrolledUsers),
  "device-commands": screen(async () => {
    const [initial, options] = await Promise.all([apiFetch("/settings/device-commands"), apiFetch("/settings/devices/options")]);
    return { initial, options };
  }, CommandQueue),
};

export { DEVICE_SCREENS };
