# 09-Seed-Data-Catalog.md
# Seed and Master Data Catalog
## Garment Industry Machinery Asset & Maintenance Management SaaS

**Version:** 1.0
**Status:** Accepted
**Companion to:** `02-Database-ERD.md`, `06-Data-Dictionary.md`

---

## 1. Purpose

A maintenance system with empty master data is unusable. A factory will not sit down and invent 60 failure codes before it can log its first breakdown, and a sales demo against an empty database sells nothing.

This catalog defines two seed layers:

| Layer | Scope | Editable by tenant |
|---|---|---|
| **Platform seed** | Permissions, roles, setting definitions, locales, units | No |
| **Industry seed** | Garment-specific asset types, failure codes, reason codes, maintenance types, checklist templates, labor grades | Yes, copied into the tenant on provisioning |

The industry seed is **copied**, not referenced. A factory that renames `NEEDLE_BREAK` to something its technicians actually say must be able to, without affecting any other tenant.

Every seeded row carries an English and a Bengali label through `translations` (SRS 48).

---

## 2. Asset Types and Categories

### 2.1 Sewing

| Type code | Category codes |
|---|---|
| `SEWING` | `LOCKSTITCH`, `OVERLOCK`, `FLATLOCK`, `CHAINSTITCH`, `BARTACK`, `BUTTONHOLE`, `BUTTON_ATTACH`, `FEED_OF_ARM`, `ZIGZAG`, `BLIND_STITCH`, `KANSAI`, `SNAP_BUTTON` |

### 2.2 Cutting and Spreading

| Type code | Category codes |
|---|---|
| `CUTTING` | `STRAIGHT_KNIFE`, `BAND_KNIFE`, `ROUND_KNIFE`, `AUTO_CUTTER`, `CAD_PLOTTER`, `SPREADER`, `END_CUTTER`, `FUSING_MACHINE` |

### 2.3 Finishing

| Type code | Category codes |
|---|---|
| `FINISHING` | `STEAM_IRON`, `VACUUM_TABLE`, `STEAM_PRESS`, `FORM_FINISHER`, `THREAD_SUCKER`, `NEEDLE_DETECTOR`, `METAL_DETECTOR`, `TAGGING_MACHINE` |

### 2.4 Washing and Dyeing

| Type code | Category codes |
|---|---|
| `WET_PROCESS` | `WASHING_MACHINE`, `HYDRO_EXTRACTOR`, `TUMBLE_DRYER`, `DYEING_MACHINE`, `SAMPLE_DYEING`, `CURING_OVEN`, `SANDBLAST_CABIN`, `OZONE_MACHINE` |

### 2.5 Embroidery and Printing

| Type code | Category codes |
|---|---|
| `EMBROIDERY` | `MULTI_HEAD_EMBROIDERY`, `SINGLE_HEAD_EMBROIDERY`, `SEQUIN_DEVICE` |
| `PRINTING` | `SCREEN_PRINT_TABLE`, `AUTO_SCREEN_PRINTER`, `HEAT_TRANSFER_PRESS`, `DTG_PRINTER`, `FLASH_CURE`, `CONVEYOR_DRYER` |

### 2.6 Utility and Facility

These carry the highest criticality in most factories: when a boiler or generator stops, every line stops.

| Type code | Category codes | Typical criticality |
|---|---|---|
| `UTILITY` | `BOILER`, `GENERATOR`, `AIR_COMPRESSOR`, `AIR_DRYER`, `CHILLER`, `COOLING_TOWER`, `WATER_PUMP`, `ETP_UNIT`, `SUBSTATION`, `TRANSFORMER`, `UPS`, `STABILIZER` | `CRITICAL` |
| `HVAC` | `AHU`, `EXHAUST_FAN`, `HUMIDIFIER`, `SPLIT_AC`, `AIR_CURTAIN` | `HIGH` |
| `MATERIAL_HANDLING` | `TROLLEY`, `HANGER_SYSTEM`, `CONVEYOR`, `FORKLIFT`, `HOIST` | `MEDIUM` |
| `SAFETY` | `FIRE_PUMP`, `FIRE_EXTINGUISHER`, `SMOKE_DETECTOR`, `EMERGENCY_LIGHT`, `FIRE_HYDRANT`, `SPRINKLER` | `CRITICAL` |
| `QUALITY_LAB` | `GSM_CUTTER`, `CROCKMETER`, `TENSILE_TESTER`, `LIGHT_BOX`, `WEIGHING_SCALE` | `MEDIUM` |

`SAFETY` assets are seeded as `CRITICAL` because their failure is discovered during an inspection or an emergency, not during production.

---

## 3. Failure Categories and Codes

Failure codes are the input to root cause analysis and to the "which machine keeps failing" report. Seeded with the vocabulary a garment maintenance team already uses.

### 3.1 Mechanical

| Code | English | Bengali |
|---|---|---|
| `NEEDLE_BREAK` | Needle breakage | সুই ভাঙা |
| `THREAD_BREAK` | Thread breakage | সুতা ছেঁড়া |
| `HOOK_WORN` | Rotary hook worn or damaged | হুক ক্ষয় |
| `FEED_DOG_WORN` | Feed dog worn | ফিড ডগ ক্ষয় |
| `PRESSER_FOOT_FAULT` | Presser foot misaligned or damaged | প্রেসার ফুট সমস্যা |
| `TIMING_OUT` | Machine timing out of adjustment | টাইমিং সমস্যা |
| `TENSION_FAULT` | Thread tension fault | টেনশন সমস্যা |
| `BOBBIN_CASE_FAULT` | Bobbin case damaged | ববিন কেস সমস্যা |
| `BEARING_FAILURE` | Bearing failure | বেয়ারিং নষ্ট |
| `BELT_BROKEN` | Belt broken or slipping | বেল্ট ছেঁড়া |
| `GEAR_DAMAGE` | Gear damage | গিয়ার ক্ষতি |
| `SHAFT_BENT` | Shaft bent | শ্যাফট বাঁকা |
| `BLADE_BLUNT` | Cutting blade blunt or chipped | ব্লেড ভোঁতা |
| `LUBRICATION_FAILURE` | Lubrication failure or oil leak | তেল সমস্যা |
| `VIBRATION_ABNORMAL` | Abnormal vibration or noise | অস্বাভাবিক কম্পন |

### 3.2 Electrical

| Code | English | Bengali |
|---|---|---|
| `MOTOR_FAILURE` | Motor failure | মোটর নষ্ট |
| `SERVO_FAULT` | Servo motor or driver fault | সার্ভো সমস্যা |
| `CAPACITOR_FAILURE` | Capacitor failure | ক্যাপাসিটর নষ্ট |
| `WIRING_FAULT` | Wiring fault or short circuit | ওয়্যারিং সমস্যা |
| `SWITCH_FAULT` | Switch or relay fault | সুইচ সমস্যা |
| `SENSOR_FAULT` | Sensor fault | সেন্সর সমস্যা |
| `PCB_FAILURE` | Control board failure | কন্ট্রোল বোর্ড নষ্ট |
| `DISPLAY_FAULT` | Display or panel fault | ডিসপ্লে সমস্যা |
| `OVERHEATING` | Overheating | অতিরিক্ত গরম |
| `POWER_FLUCTUATION_DAMAGE` | Damage from voltage fluctuation | ভোল্টেজ সমস্যায় ক্ষতি |

### 3.3 Pneumatic and Hydraulic

| Code | English | Bengali |
|---|---|---|
| `AIR_LEAK` | Compressed air leak | বাতাস লিক |
| `CYLINDER_FAULT` | Pneumatic cylinder fault | সিলিন্ডার সমস্যা |
| `SOLENOID_FAULT` | Solenoid valve fault | সলিনয়েড ভালভ সমস্যা |
| `PRESSURE_LOW` | Insufficient air pressure | প্রেসার কম |
| `HOSE_DAMAGE` | Hose damaged | হোস নষ্ট |
| `HYDRAULIC_LEAK` | Hydraulic oil leak | হাইড্রলিক লিক |

### 3.4 Utility and Steam

| Code | English | Bengali |
|---|---|---|
| `STEAM_LEAK` | Steam leak | স্টিম লিক |
| `STEAM_TRAP_FAULT` | Steam trap fault | স্টিম ট্র্যাপ সমস্যা |
| `BOILER_PRESSURE_FAULT` | Boiler pressure abnormal | বয়লার প্রেসার সমস্যা |
| `WATER_LEVEL_FAULT` | Water level control fault | পানির লেভেল সমস্যা |
| `FUEL_SUPPLY_FAULT` | Fuel supply interruption | জ্বালানি সরবরাহ সমস্যা |
| `GENERATOR_START_FAILURE` | Generator fails to start | জেনারেটর চালু হয় না |
| `COMPRESSOR_TRIP` | Compressor tripped | কম্প্রেসার ট্রিপ |

### 3.5 Operational and Other

| Code | English | Bengali |
|---|---|---|
| `OPERATOR_ERROR` | Operator error | অপারেটর ভুল |
| `IMPROPER_SETTING` | Incorrect machine setting | ভুল সেটিং |
| `MISSING_PM` | Missed preventive maintenance | পিএম বাদ পড়া |
| `WRONG_SPARE_USED` | Incorrect spare part fitted | ভুল পার্টস ব্যবহার |
| `MATERIAL_JAM` | Fabric or material jam | কাপড় আটকে যাওয়া |
| `AGING` | End of service life | মেয়াদ শেষ |
| `UNKNOWN` | Cause not determined | কারণ অজানা |

`UNKNOWN` is seeded deliberately. Without it, technicians under pressure pick a wrong code, and wrong data is worse than absent data. A report tracks the share of `UNKNOWN` closures as a data-quality metric.

---

## 4. Root Causes

| Code | English | Bengali |
|---|---|---|
| `NORMAL_WEAR` | Normal wear and tear | স্বাভাবিক ক্ষয় |
| `INADEQUATE_LUBRICATION` | Inadequate lubrication | পর্যাপ্ত তেল না দেওয়া |
| `MISSED_MAINTENANCE` | Preventive maintenance not performed | পিএম করা হয়নি |
| `IMPROPER_INSTALLATION` | Improper installation or alignment | ভুল ইনস্টলেশন |
| `IMPROPER_OPERATION` | Improper operation | ভুলভাবে চালানো |
| `INSUFFICIENT_TRAINING` | Operator not adequately trained | প্রশিক্ষণের অভাব |
| `POOR_QUALITY_SPARE` | Substandard spare part | নিম্নমানের পার্টস |
| `POWER_QUALITY` | Voltage fluctuation or power quality | বিদ্যুতের মান |
| `ENVIRONMENTAL` | Dust, humidity, or temperature | পরিবেশগত কারণ |
| `OVERLOAD` | Machine run beyond rated capacity | অতিরিক্ত লোড |
| `DESIGN_LIMITATION` | Design or model limitation | ডিজাইনের সীমাবদ্ধতা |
| `MANUFACTURING_DEFECT` | Manufacturing defect | উৎপাদনগত ত্রুটি |
| `END_OF_LIFE` | Asset past useful life | মেয়াদোত্তীর্ণ |
| `UNDETERMINED` | Not determined | নির্ণয় করা যায়নি |

`MISSED_MAINTENANCE` and `POOR_QUALITY_SPARE` are the two causes that most often justify the system's own cost, so they are seeded explicitly rather than left to free text.

---

## 5. Downtime Reason Codes

Each maps to a class from Data Dictionary 2.5 and carries whether it counts against availability.

| Code | Class | Counts | English |
|---|---|---|---|
| `MACHINE_BREAKDOWN` | `UNPLANNED` | Yes | Machine breakdown |
| `ELECTRICAL_FAULT` | `UNPLANNED` | Yes | Electrical fault |
| `AWAITING_SPARE` | `UNPLANNED` | Yes | Waiting for spare part |
| `AWAITING_TECHNICIAN` | `UNPLANNED` | Yes | Waiting for technician |
| `SETTING_ADJUSTMENT` | `UNPLANNED` | Yes | Machine setting or adjustment |
| `PLANNED_PM` | `PLANNED` | No | Scheduled preventive maintenance |
| `PLANNED_OVERHAUL` | `PLANNED` | No | Planned overhaul |
| `INSTALLATION` | `PLANNED` | No | Installation or relocation |
| `POWER_OUTAGE` | `EXTERNAL` | No | Grid power outage |
| `GAS_SUPPLY_FAILURE` | `EXTERNAL` | No | Gas supply interruption |
| `MATERIAL_SHORTAGE` | `EXTERNAL` | No | Material not available |
| `NO_OPERATOR` | `EXTERNAL` | No | Operator not available |
| `STYLE_CHANGEOVER` | `EXTERNAL` | No | Style or line changeover |
| `SHIFT_BREAK` | `NON_OPERATING` | No | Break or shift end |
| `HOLIDAY` | `NON_OPERATING` | No | Factory holiday |

`AWAITING_SPARE` counting against availability is the point: it makes the cost of an understocked store visible as downtime rather than hiding it inside repair time.

---

## 6. Maintenance Types

| Code | English | Bengali | Default priority |
|---|---|---|---|
| `PREVENTIVE` | Preventive maintenance | প্রতিরোধমূলক রক্ষণাবেক্ষণ | `MEDIUM` |
| `CORRECTIVE` | Corrective maintenance | সংশোধনমূলক রক্ষণাবেক্ষণ | `HIGH` |
| `BREAKDOWN` | Breakdown repair | ব্রেকডাউন মেরামত | `HIGH` |
| `EMERGENCY` | Emergency repair | জরুরি মেরামত | `CRITICAL` |
| `INSPECTION` | Inspection | পরিদর্শন | `LOW` |
| `CALIBRATION` | Calibration | ক্যালিব্রেশন | `MEDIUM` |
| `CLEANING` | Cleaning and lubrication | পরিষ্কার ও তেল দেওয়া | `LOW` |
| `OVERHAUL` | Overhaul | ওভারহল | `MEDIUM` |
| `INSTALLATION` | Installation and commissioning | ইনস্টলেশন | `MEDIUM` |
| `CONDITION_BASED` | Condition-based maintenance | কন্ডিশন ভিত্তিক | `MEDIUM` |

---

## 7. Meter Types

| Code | Unit | Applies to |
|---|---|---|
| `RUNNING_HOURS` | `HOUR` | Almost every powered asset |
| `STITCH_COUNT` | `STITCH` | Sewing machines with a counter |
| `CYCLE_COUNT` | `CYCLE` | Presses, bartack, buttonhole |
| `PIECE_COUNT` | `PIECE` | Cutting, finishing |
| `FUEL_CONSUMED` | `LITRE` | Generator, boiler |
| `ENERGY_CONSUMED` | `KWH` | Compressor, chiller, substation |
| `WATER_CONSUMED` | `CUBIC_METRE` | Washing, dyeing, ETP |
| `DISTANCE` | `KM` | Forklift, vehicles |

---

## 8. Labor Rate Grades

Seeded with rate `0` and a currency, so a factory must set its own figures before labor cost is reported. A seeded fake rate would silently produce wrong costs.

| Code | English | Bengali | Overtime multiplier |
|---|---|---|---|
| `HELPER` | Maintenance Helper | সহকারী | 2.0 |
| `JR_TECH` | Junior Technician | জুনিয়র টেকনিশিয়ান | 2.0 |
| `TECH` | Technician | টেকনিশিয়ান | 2.0 |
| `SR_TECH` | Senior Technician | সিনিয়র টেকনিশিয়ান | 2.0 |
| `ELECTRICIAN` | Electrician | ইলেকট্রিশিয়ান | 2.0 |
| `MECHANIC` | Mechanic | মেকানিক | 2.0 |
| `ENGINEER` | Maintenance Engineer | মেইনটেন্যান্স ইঞ্জিনিয়ার | 1.5 |
| `CONTRACTOR` | External Contractor | বাইরের কন্ট্রাক্টর | n/a |

The overtime multiplier of 2.0 reflects the common Bangladesh Labour Act treatment of overtime at twice the ordinary rate. It is seeded as a default and is editable, because it is a legal and contractual matter, not a product decision.

---

## 9. Checklist Templates

Seeded as published version 1 templates, bound to an asset type. A factory clones and edits rather than starting blank.

### 9.1 Lockstitch Sewing Machine — Monthly PM

| Seq | Item | Input | Required | Tolerance |
|---|---|---|---|---|
| 1 | Machine switched off and isolated before work | `PASS_FAIL` | Yes | — |
| 2 | Clean hook race and bobbin area | `PASS_FAIL` | Yes | — |
| 3 | Oil level in reservoir | `PASS_FAIL` | Yes | — |
| 4 | Check and replace needle | `PASS_FAIL` | Yes | — |
| 5 | Feed dog condition | `PASS_FAIL` | Yes | — |
| 6 | Presser foot alignment and pressure | `PASS_FAIL` | Yes | — |
| 7 | Thread tension test on sample fabric | `PASS_FAIL` | Yes | — |
| 8 | Belt tension and condition | `PASS_FAIL` | Yes | — |
| 9 | Motor noise and temperature | `PASS_FAIL` | Yes | — |
| 10 | Stitch per inch measured | `NUMERIC` | Yes | Model-specific |
| 11 | Needle guard and eye guard fitted | `PASS_FAIL` | Yes | — |
| 12 | Earthing continuity checked | `PASS_FAIL` | Yes | — |
| 13 | Machine cleaned and work area cleared | `PASS_FAIL` | Yes | — |
| 14 | Photo of machine after service | `PHOTO` | No | — |

Items 1, 11 and 12 are safety items: they are seeded with `requires_note_on_fail` and `fail_creates_followup_work_order` enabled.

### 9.2 Generator — Monthly PM

| Seq | Item | Input | Required |
|---|---|---|---|
| 1 | Engine oil level and condition | `PASS_FAIL` | Yes |
| 2 | Coolant level | `PASS_FAIL` | Yes |
| 3 | Fuel level and leak check | `PASS_FAIL` | Yes |
| 4 | Battery voltage | `NUMERIC` | Yes |
| 5 | Air filter condition | `PASS_FAIL` | Yes |
| 6 | Belt tension | `PASS_FAIL` | Yes |
| 7 | Exhaust leak check | `PASS_FAIL` | Yes |
| 8 | No-load test run duration (minutes) | `NUMERIC` | Yes |
| 9 | Output voltage on test | `NUMERIC` | Yes |
| 10 | Frequency on test | `NUMERIC` | Yes |
| 11 | Automatic transfer switch operation | `PASS_FAIL` | Yes |
| 12 | Running hours reading | `NUMERIC` | Yes |

### 9.3 Boiler — Weekly Inspection

| Seq | Item | Input | Required |
|---|---|---|---|
| 1 | Water level indicator clear and correct | `PASS_FAIL` | Yes |
| 2 | Blowdown performed | `PASS_FAIL` | Yes |
| 3 | Safety valve tested | `PASS_FAIL` | Yes |
| 4 | Pressure gauge reading | `NUMERIC` | Yes |
| 5 | Feed water pump operation | `PASS_FAIL` | Yes |
| 6 | Steam leak inspection | `PASS_FAIL` | Yes |
| 7 | Flue gas temperature | `NUMERIC` | No |
| 8 | Chemical dosing level | `PASS_FAIL` | Yes |
| 9 | Fire and safety clearance around unit | `PASS_FAIL` | Yes |
| 10 | Operator log signed | `SIGNATURE` | Yes |

Every item on this template is seeded as required with attachment on fail, because boiler inspection is a regulated activity and an unsupported pass is worthless in an audit.

### 9.4 Air Compressor — Monthly PM

| Seq | Item | Input | Required |
|---|---|---|---|
| 1 | Oil level and condition | `PASS_FAIL` | Yes |
| 2 | Air filter cleaned or replaced | `PASS_FAIL` | Yes |
| 3 | Condensate drained | `PASS_FAIL` | Yes |
| 4 | Discharge pressure | `NUMERIC` | Yes |
| 5 | Air leak survey on distribution line | `PASS_FAIL` | Yes |
| 6 | Belt and coupling condition | `PASS_FAIL` | Yes |
| 7 | Safety valve check | `PASS_FAIL` | Yes |
| 8 | Running hours reading | `NUMERIC` | Yes |

### 9.5 Cutting Machine — Monthly PM

| Seq | Item | Input | Required |
|---|---|---|---|
| 1 | Blade condition and sharpness | `PASS_FAIL` | Yes |
| 2 | Blade guard fitted and functional | `PASS_FAIL` | Yes |
| 3 | Sharpening stone condition | `PASS_FAIL` | Yes |
| 4 | Base plate condition | `PASS_FAIL` | Yes |
| 5 | Motor and gear noise | `PASS_FAIL` | Yes |
| 6 | Lubrication points serviced | `PASS_FAIL` | Yes |
| 7 | Power cable condition | `PASS_FAIL` | Yes |
| 8 | Emergency stop functional | `PASS_FAIL` | Yes |

---

## 10. Spare Part Categories

| Code | English |
|---|---|
| `NEEDLE` | Needles |
| `HOOK_LOOPER` | Hooks and loopers |
| `FEED_PART` | Feed dogs and throat plates |
| `PRESSER_PART` | Presser feet and guides |
| `BOBBIN_PART` | Bobbins and bobbin cases |
| `BLADE_KNIFE` | Blades and knives |
| `BEARING` | Bearings and bushings |
| `BELT` | Belts |
| `GEAR_SHAFT` | Gears and shafts |
| `MOTOR_PART` | Motors and servo parts |
| `ELECTRICAL` | Electrical components |
| `ELECTRONIC` | Boards, sensors, displays |
| `PNEUMATIC` | Pneumatic components |
| `HYDRAULIC` | Hydraulic components |
| `FILTER` | Filters |
| `LUBRICANT` | Oils and greases |
| `FASTENER` | Screws, nuts, springs |
| `STEAM_PART` | Steam and boiler parts |
| `SAFETY_PART` | Safety and guard components |
| `CONSUMABLE` | General consumables |

---

## 11. Default Maintenance Plans

Seeded as inactive templates per asset type. A factory activates and adjusts them rather than designing a PM programme from nothing.

| Asset type | Trigger | Logic | Interval | Template |
|---|---|---|---|---|
| `SEWING` | `COMBINED` | `OR` | 30 days or 500 running hours | 9.1 |
| `CUTTING` | `TIME` | — | 30 days | 9.5 |
| `EMBROIDERY` | `COMBINED` | `OR` | 30 days or 1,000,000 stitches | Clone of 9.1 |
| `UTILITY` / `GENERATOR` | `COMBINED` | `OR` | 30 days or 250 running hours | 9.2 |
| `UTILITY` / `BOILER` | `TIME` | — | 7 days | 9.3 |
| `UTILITY` / `AIR_COMPRESSOR` | `COMBINED` | `OR` | 30 days or 500 running hours | 9.4 |
| `SAFETY` | `TIME` | — | 30 days | Safety inspection |
| `HVAC` | `TIME` | — | 90 days | HVAC service |
| `QUALITY_LAB` | `TIME` | — | 180 days | Calibration |

All are seeded with `requires_shutdown = true` and `non_working_day_policy = NEXT_WORKING_DAY`, so PM does not land on a Friday and immediately count as overdue.

---

## 12. Settings Definitions

Seeded per SRS 53.2, with the defaults listed there. Every key carries a description and a value type; a key absent from `setting_definitions` cannot be set (ADR-054).

---

## 13. Roles and Permissions

Platform seed. The 12 roles of SRS 5 with the default permission matrix of Handbook 2. Seeded roles are not editable; a tenant clones one to customize it.

A seed test asserts three things, and fails the build otherwise:

1. Every permission in the catalog is granted to at least one seeded role.
2. `VIEWER` and `AUDITOR` hold no write permission.
3. Every role's permission set is a subset of the catalog, so a typo cannot create a permission that no policy checks.

---

## 14. Demo Tenant

A `--demo` seeder provisions one realistic factory for sales demonstrations, UAT, and load testing. Never installed in production.

| Entity | Volume |
|---|---|
| Company | 1, base currency `BDT`, locale `bn` |
| Factories | 2 (Dhaka Unit 1, Gazipur Unit 2) |
| Locations | Buildings, floors, 6 sewing lines, cutting, finishing, store |
| Users | 12 across every role |
| Technicians | 8 across 4 grades |
| Assets | 450 (400 sewing, 30 cutting and finishing, 20 utility) |
| Spare parts | 120 with opening balances |
| Maintenance plans | Active on all asset types |
| History | 12 months of completed work orders, breakdowns, meter readings, and cost entries |

The 12 months of history matters most: a dashboard demonstrated against an empty database shows nothing, and MTBF, availability, and cost trend cannot be evaluated without it. The generated history includes deliberate patterns — one machine with recurring `MOTOR_FAILURE`, one line with elevated `AWAITING_SPARE` downtime — so the analytics have something true to find.
