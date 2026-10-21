import { AddIcon } from '@solar-icons/react/linear/add';
import { AltArrowDownIcon } from '@solar-icons/react/linear/alt-arrow-down';
import { AltArrowRightIcon } from '@solar-icons/react/linear/alt-arrow-right';
import { ArchiveIcon } from '@solar-icons/react/linear/archive';
import { ArrowLeftIcon } from '@solar-icons/react/linear/arrow-left';
import { BellIcon } from '@solar-icons/react/linear/bell';
import { CalendarIcon } from '@solar-icons/react/linear/calendar';
import { CarIcon } from '@solar-icons/react/linear/car';
import { CheckCircleIcon } from '@solar-icons/react/linear/check-circle';
import { ClipboardIcon } from '@solar-icons/react/linear/clipboard';
import { CloseIcon } from '@solar-icons/react/linear/close';
import { CloudBoltIcon } from '@solar-icons/react/linear/cloud-bolt';
import { CloudRainIcon } from '@solar-icons/react/linear/cloud-rain';
import { CloudSunIcon } from '@solar-icons/react/linear/cloud-sun';
import { Columns3Icon } from '@solar-icons/react/linear/columns-3';
import { DangerTriangleIcon } from '@solar-icons/react/linear/danger-triangle';
import { DocumentTextIcon } from '@solar-icons/react/linear/document-text';
import { DownloadIcon } from '@solar-icons/react/linear/download';
import { EyeClosedIcon } from '@solar-icons/react/linear/eye-closed';
import { EyeIcon } from '@solar-icons/react/linear/eye';
import { FlagIcon } from '@solar-icons/react/linear/flag';
import { FogIcon } from '@solar-icons/react/linear/fog';
import { Grid2x2Icon } from '@solar-icons/react/linear/grid-2x2';
import { GripVerticalIcon } from '@solar-icons/react/linear/grip-vertical';
import { HamburgerMenuIcon } from '@solar-icons/react/linear/hamburger-menu';
import { InfoCircleIcon } from '@solar-icons/react/linear/info-circle';
import { KeyIcon } from '@solar-icons/react/linear/key';
import { LetterIcon } from '@solar-icons/react/linear/letter';
import { LinkIcon } from '@solar-icons/react/linear/link';
import { ListIcon } from '@solar-icons/react/linear/list';
import { MagnifierIcon } from '@solar-icons/react/linear/magnifier';
import { MapPointIcon } from '@solar-icons/react/linear/map-point';
import { PenIcon } from '@solar-icons/react/linear/pen';
import { SettingsIcon } from '@solar-icons/react/linear/settings';
import { SledgehammerIcon } from '@solar-icons/react/linear/sledgehammer';
import { SunIcon } from '@solar-icons/react/linear/sun';
import { TicketIcon } from '@solar-icons/react/linear/ticket';
import { TrashBinTrashIcon } from '@solar-icons/react/linear/trash-bin-trash';
import { UndoLeftIcon } from '@solar-icons/react/linear/undo-left';
import { UsersGroupTwoRoundedIcon } from '@solar-icons/react/linear/users-group-two-rounded';
import { WaterIcon } from '@solar-icons/react/linear/water';

// One application-level name for each semantic role keeps view code readable
// while ensuring every shared UI icon comes from the Solar Linear catalogue.
const ICONS = {
  bell: BellIcon,
  key: KeyIcon,
  mail: LetterIcon,
  calendar: CalendarIcon,
  vehicle: CarIcon,
  boat: WaterIcon,
  clipboard: ClipboardIcon,
  search: MagnifierIcon,
  eye: EyeIcon,
  eyeOff: EyeClosedIcon,
  wrench: SledgehammerIcon,
  tools: SledgehammerIcon,
  checkCircle: CheckCircleIcon,
  flag: FlagIcon,
  link: LinkIcon,
  info: InfoCircleIcon,
  close: CloseIcon,
  arrowLeft: ArrowLeftIcon,
  chevronDown: AltArrowDownIcon,
  chevronRight: AltArrowRightIcon,
  plus: AddIcon,
  menu: HamburgerMenuIcon,
  grid: Grid2x2Icon,
  list: ListIcon,
  columns: Columns3Icon,
  gripVertical: GripVerticalIcon,
  edit: PenIcon,
  trash: TrashBinTrashIcon,
  archive: ArchiveIcon,
  download: DownloadIcon,
  undo: UndoLeftIcon,
  ticket: TicketIcon,
  pin: MapPointIcon,
  gear: SettingsIcon,
  users: UsersGroupTwoRoundedIcon,
  document: DocumentTextIcon,
  sun: SunIcon,
  cloudSun: CloudSunIcon,
  cloudFog: FogIcon,
  cloudRain: CloudRainIcon,
  cloudLightning: CloudBoltIcon,
  alert: DangerTriangleIcon,
};

export default function Icon({ name, size = 16, strokeWidth = 1.8, className, style }) {
  const SolarIcon = ICONS[name];
  if (!SolarIcon) return null;

  return (
    <SolarIcon
      aria-hidden="true"
      className={className}
      size={size}
      strokeWidth={strokeWidth}
      style={style}
    />
  );
}
