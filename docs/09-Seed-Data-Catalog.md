# 09-Seed-Data-Catalog.md
# Seed and Master Data Catalog
## Textile & Garment Industry Machinery Asset & Maintenance Management SaaS

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
| **Industry seed** | Textile-specific asset types, failure codes, reason codes, maintenance types, checklist templates, labor grades | Yes, copied into the tenant on provisioning |

The industry seed is **copied**, not referenced. A factory that renames `NEEDLE_BREAK` to something its technicians actually say must be able to, without affecting any other tenant.

Every seeded row carries an English and a Bengali label through `translations` (SRS 48).

---

## 2. Asset Types and Categories

The catalog covers a composite mill, not only a sewing floor: yarn, knitting, dyeing and fabric finishing run under the same roof as cutting and sewing in most Bangladeshi groups, and a maintenance system that knows only the sewing floor is one the dye house keeps a spreadsheet beside.

### 2.0 Yarn Preparation and Knitting

| Type code | Category codes | Typical criticality |
|---|---|---|
| `YARN_PREP` | `SOFT_WINDING`, `REWINDING`, `YARN_CONDITIONING`, `WARPING`, `YARN_CLEARER` | `MEDIUM` |
| `KNITTING` | `CIRCULAR_SINGLE_JERSEY`, `CIRCULAR_INTERLOCK`, `CIRCULAR_RIB`, `FLEECE_TERRY`, `CIRCULAR_JACQUARD`, `FLAT_KNITTING`, `COLLAR_CUFF`, `WARP_KNITTING`, `SEAMLESS_KNITTING`, `SOCKS_KNITTING`, `LYCRA_FEEDER_UNIT` | `HIGH` |

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

### 2.4 Dyeing, Fabric Finishing and Garment Washing

Fabric and yarn dyeing are their own type. A dye house is not a washing plant with extra vessels: different machines, different failures, and a different person answering for them. `DYEING` is seeded `CRITICAL` because a vessel that stops mid-batch does not pause, it ruins the batch inside it.

| Type code | Category codes | Typical criticality |
|---|---|---|
| `DYEING` | `SOFT_FLOW_DYEING`, `HT_HP_DYEING`, `JET_DYEING`, `WINCH_DYEING`, `JIGGER_DYEING`, `PAD_BATCH`, `CONTINUOUS_RANGE`, `SCOURING_BLEACHING`, `YARN_PACKAGE_DYEING`, `HANK_DYEING`, `RF_DRYER`, `COLOR_KITCHEN`, `DOSING_UNIT` | `CRITICAL` |
| `FABRIC_FINISHING` | `STENTER`, `COMPACTOR_OPEN`, `COMPACTOR_TUBULAR`, `DEWATERING`, `SLITTING`, `RELAX_DRYER`, `FABRIC_TUMBLE_DRYER`, `CALENDER`, `RAISING`, `SUEDING`, `SHEARING`, `SANFORIZING`, `FABRIC_INSPECTION`, `FABRIC_ROLLING`, `BALING_PRESS` | `HIGH` |
| `WET_PROCESS` | `WASHING_MACHINE`, `HYDRO_EXTRACTOR`, `TUMBLE_DRYER`, `GARMENT_DYEING`, `CURING_OVEN`, `SANDBLAST_CABIN`, `OZONE_MACHINE`, `LASER_MACHINE` | `HIGH` |

### 2.5 Embroidery and Printing

| Type code | Category codes |
|---|---|
| `EMBROIDERY` | `MULTI_HEAD_EMBROIDERY`, `SINGLE_HEAD_EMBROIDERY`, `SEQUIN_DEVICE` |
| `PRINTING` | `SCREEN_PRINT_TABLE`, `AUTO_SCREEN_PRINTER`, `HEAT_TRANSFER_PRESS`, `DTG_PRINTER`, `FLASH_CURE`, `CONVEYOR_DRYER` |

### 2.6 Utility and Facility

These carry the highest criticality in most factories: when a boiler or generator stops, every line stops.

| Type code | Category codes | Typical criticality |
|---|---|---|
| `UTILITY` | `BOILER`, `THERMAL_OIL_HEATER`, `CONDENSATE_RECOVERY`, `GENERATOR`, `GAS_BOOSTER`, `AIR_COMPRESSOR`, `AIR_DRYER`, `CHILLER`, `COOLING_TOWER`, `WATER_PUMP`, `DEEP_TUBEWELL`, `WTP_UNIT`, `SOFTENER_PLANT`, `ETP_UNIT`, `SUBSTATION`, `TRANSFORMER`, `UPS`, `STABILIZER` | `CRITICAL` |
| `HVAC` | `AHU`, `EXHAUST_FAN`, `HUMIDIFIER`, `SPLIT_AC`, `AIR_CURTAIN` | `HIGH` |
| `MATERIAL_HANDLING` | `TROLLEY`, `HANGER_SYSTEM`, `CONVEYOR`, `FORKLIFT`, `HOIST` | `MEDIUM` |
| `SAFETY` | `FIRE_PUMP`, `FIRE_EXTINGUISHER`, `SMOKE_DETECTOR`, `EMERGENCY_LIGHT`, `FIRE_HYDRANT`, `SPRINKLER` | `CRITICAL` |
| `QUALITY_LAB` | `GSM_CUTTER`, `CROCKMETER`, `TENSILE_TESTER`, `LIGHT_BOX`, `WEIGHING_SCALE`, `SPECTROPHOTOMETER`, `LAB_DYEING`, `LAB_STENTER`, `WASH_FASTNESS_TESTER`, `PILLING_TESTER`, `SHRINKAGE_DRYER`, `PH_METER` | `MEDIUM` |

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

### 3.1a Knitting

Kept apart from the general mechanical list because these are the codes a knitting fitter actually says, and because grouping them is what makes "which machine keeps dropping needles" answerable.

| Code | English | Bengali |
|---|---|---|
| `KNIT_NEEDLE_BROKEN` | Knitting needle broken or bent | নিটিং সুই ভাঙা |
| `SINKER_BROKEN` | Sinker broken or worn | সিংকার ভাঙা |
| `CAM_DAMAGE` | Cam damaged or loose | ক্যাম ক্ষতিগ্রস্ত |
| `CYLINDER_DIAL_SCRATCH` | Cylinder or dial scratched | সিলিন্ডার/ডায়াল আঁচড় |
| `YARN_FEEDER_FAULT` | Yarn feeder fault | ইয়ার্ন ফিডার সমস্যা |
| `POSITIVE_FEEDER_BELT` | Positive feeder belt broken or slipping | পজিটিভ ফিডার বেল্ট সমস্যা |
| `LYCRA_FEEDER_FAULT` | Lycra feeder fault | লাইক্রা ফিডার সমস্যা |
| `STOP_MOTION_FAULT` | Stop motion or yarn detector fault | স্টপ মোশন সমস্যা |
| `TAKE_DOWN_TENSION` | Take-down tension fault | টেক-ডাউন টেনশন সমস্যা |
| `DROPPED_STITCH` | Dropped stitch or hole in fabric | স্টিচ পড়ে যাওয়া |
| `OIL_PUMP_FAULT` | Needle oil pump or spray fault | অয়েল পাম্প সমস্যা |
| `LINT_ACCUMULATION` | Lint accumulation or blower fault | লিন্ট জমা / ব্লোয়ার সমস্যা |

### 3.1b Dyeing and Finishing

| Code | English | Bengali |
|---|---|---|
| `PUMP_SEAL_LEAK` | Main pump seal leak | পাম্প সিল লিক |
| `IMPELLER_DAMAGE` | Pump impeller damaged | ইম্পেলার ক্ষতিগ্রস্ত |
| `NOZZLE_BLOCKED` | Dyeing nozzle blocked or worn | নোজল ব্লক |
| `REEL_DRIVE_FAULT` | Reel or winch drive fault | রিল ড্রাইভ সমস্যা |
| `DOOR_SEAL_LEAK` | Vessel door seal leak | ভেসেল ডোর সিল লিক |
| `ROPE_TANGLE` | Fabric rope entangled in vessel | কাপড় পেঁচিয়ে যাওয়া |
| `HEAT_EXCHANGER_SCALING` | Heat exchanger scaled or leaking | হিট এক্সচেঞ্জার স্কেলিং |
| `STENTER_CHAIN_FAULT` | Stenter chain, clip or pin fault | স্টেন্টার চেইন সমস্যা |
| `STENTER_BURNER_FAULT` | Stenter burner or heating fault | স্টেন্টার বার্নার সমস্যা |
| `PADDER_ROLLER_WEAR` | Padder roller worn or uneven | প্যাডার রোলার ক্ষয় |
| `BLANKET_DAMAGE` | Compactor blanket or felt damaged | কম্প্যাক্টর ব্ল্যাঙ্কেট ক্ষতি |
| `SELVEDGE_UNCURLER_FAULT` | Selvedge uncurler fault | সেলভেজ আনকার্লার সমস্যা |
| `FABRIC_GUIDER_FAULT` | Fabric guider or centring fault | ফেব্রিক গাইডার সমস্যা |
| `SHADE_VARIATION` | Shade variation traced to the machine | মেশিনজনিত শেড ভ্যারিয়েশন |

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

### 3.2a Process and Instrumentation

In a dye house instrumentation is not a footnote. A temperature probe reading two degrees low does not stop the machine; it produces a batch in the wrong shade, and the loss is counted in fabric rather than in downtime. Coding these separately is what lets a dye house see it at all.

| Code | English | Bengali |
|---|---|---|
| `TEMP_SENSOR_DRIFT` | Temperature sensor drift or failure | তাপমাত্রা সেন্সর ড্রিফট |
| `PH_PROBE_FAULT` | pH probe fault | পিএইচ প্রোব সমস্যা |
| `FLOW_METER_FAULT` | Flow meter fault | ফ্লো মিটার সমস্যা |
| `LEVEL_SENSOR_FAULT` | Level sensor fault | লেভেল সেন্সর সমস্যা |
| `PRESSURE_TRANSMITTER_FAULT` | Pressure transmitter fault | প্রেসার ট্রান্সমিটার সমস্যা |
| `DOSING_VALVE_FAULT` | Dosing valve fault | ডোজিং ভালভ সমস্যা |
| `CONTROL_VALVE_PASSING` | Control valve passing or stuck | কন্ট্রোল ভালভ পাসিং |
| `PLC_FAULT` | PLC or process controller fault | পিএলসি সমস্যা |
| `RECIPE_DISPENSING_ERROR` | Recipe or dispensing error | রেসিপি/ডিসপেন্সিং ভুল |
| `CALIBRATION_OVERDUE` | Instrument out of calibration | ক্যালিব্রেশন মেয়াদোত্তীর্ণ |

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
| `CHILLER_TRIP` | Chiller tripped | চিলার ট্রিপ |
| `THERMAL_OIL_FAULT` | Thermal oil heater fault | থার্মাল অয়েল হিটার সমস্যা |
| `WATER_QUALITY_FAULT` | Treated water out of limit | পানির মান সীমার বাইরে |
| `SOFTENER_RESIN_EXHAUSTED` | Softener resin exhausted | সফটেনার রেজিন শেষ |
| `ETP_DOSING_FAULT` | ETP dosing or blower fault | ইটিপি ডোজিং সমস্যা |
| `CONDENSATE_RECOVERY_FAULT` | Condensate recovery fault | কনডেনসেট রিকভারি সমস্যা |

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
| `BATCH_CHANGEOVER` | `EXTERNAL` | No | Batch or shade changeover |
| `QUALITY_CHANGEOVER` | `EXTERNAL` | No | Yarn or fabric quality changeover |
| `STEAM_UNAVAILABLE` | `EXTERNAL` | No | Steam not available |
| `WATER_UNAVAILABLE` | `EXTERNAL` | No | Water supply interruption |
| `EFFLUENT_LIMIT` | `EXTERNAL` | No | Stopped for effluent limit |
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
| `STEAM_CONSUMED` | `KG` | Dyeing, stenter, boiler |
| `BATCH_COUNT` | `BATCH` | Dyeing vessels |
| `FABRIC_LENGTH` | `METRE` | Finishing, inspection |
| `FABRIC_WEIGHT` | `KG` | Knitting, dyeing |

A dye vessel's service interval is counted in batches and a knitting machine's in kilograms off the take-down, neither of them in hours.

---

## 8. Technician Areas

Nothing is seeded here, and there are no labour rate grades to seed: maintenance labour has no cost, because technicians are salaried employees (ADR-065).

What a technician carries instead is the part of the mill they look after, and that comes from the company's own floor plan rather than from a platform list — a dyeing technician is attached to that company's dyeing department, which only that company has.

The specialisation field is free text for the same reason: "Dyeing machines", "Sewing machines", "Boiler and generator" are what a factory writes on its own roster, and a fixed list would be wrong for the next factory.

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

Grouped by where the part is fitted rather than by what it is made of, because the question a store answers is "what is knitting costing us this quarter", not "how much steel do we hold".

| Code | English | Bengali |
|---|---|---|
| `SEWING_PARTS` | Sewing machine parts | সেলাই মেশিনের পার্টস |
| `CUTTING_PARTS` | Cutting machine parts | কাটিং মেশিনের পার্টস |
| `KNITTING_PARTS` | Knitting parts (needles, sinkers, cams) | নিটিং পার্টস |
| `DYEING_PARTS` | Dyeing parts (pumps, seals, nozzles) | ডাইং পার্টস |
| `FINISHING_PARTS` | Finishing parts (chains, clips, blankets) | ফিনিশিং পার্টস |
| `ELECTRICAL` | Electrical | বৈদ্যুতিক |
| `MECHANICAL` | Mechanical | যান্ত্রিক |
| `INSTRUMENTATION` | Instrumentation and sensors | ইনস্ট্রুমেন্টেশন ও সেন্সর |
| `BEARINGS` | Bearings and bushes | বেয়ারিং ও বুশ |
| `BELTS_CHAINS` | Belts and chains | বেল্ট ও চেইন |
| `SEALS_GASKETS` | Seals and gaskets | সিল ও গ্যাসকেট |
| `VALVES_FITTINGS` | Valves and fittings | ভালভ ও ফিটিংস |
| `FILTERS` | Filters and strainers | ফিল্টার ও স্ট্রেইনার |
| `PNEUMATIC` | Pneumatic | নিউম্যাটিক |
| `HYDRAULIC` | Hydraulic | হাইড্রলিক |
| `LUBRICANTS` | Lubricants and oils | তেল ও লুব্রিক্যান্ট |
| `FASTENERS` | Fasteners | নাট-বোল্ট |
| `STEAM_UTILITY` | Steam and utility | স্টিম ও ইউটিলিটি |
| `UTILITY_CHEMICALS` | Boiler and water treatment chemicals | বয়লার ও পানি ট্রিটমেন্ট কেমিক্যাল |
| `SAFETY` | Safety equipment | নিরাপত্তা সরঞ্জাম |
| `CONSUMABLES` | Consumables | ভোগ্য সামগ্রী |

Needles and sinkers are bought by the thousand and consumed by the hundred, so `KNITTING_PARTS` is separated from general mechanical stock: a knitting floor that cannot see its needle spend separately cannot see its largest recurring cost.

Dyes and process chemicals are production stock and are not held here. `UTILITY_CHEMICALS` covers boiler and water treatment only.

---

### 9.6 Circular Knitting Machine — Monthly PM

`PM-KNITTING-MONTHLY`, 90 minutes, 15 items. Isolation and lock-out (safety); lint blown from cylinder, dial and creel; needle and sinker inspection with a count of needles replaced; cam condition and mounting; cylinder and dial surface; oil spray nozzles and level; positive feeder belts; yarn stop motion tested; lycra feeder alignment; take-down tension; fabric width and machine speed recorded; guards and emergency stop (safety); a sample course run and checked for holes.

### 9.7 Soft Flow Dyeing Machine — Monthly PM

`PM-DYEING-MONTHLY`, 120 minutes, 16 items. The longest seeded checklist, deliberately: a dye vessel is the one machine where a fault does not stop production, it produces a wrong shade, and the loss is counted in rejected fabric rather than downtime. Drain, isolate and lock-out (safety); main pump seal, impeller and casing; nozzle; reel drive and lubrication; door seal and locking interlock (safety); heat exchanger cleaned and checked for scale; steam and cooling valves for passing; filters and strainers; dosing pump and valve; temperature probe against reference (±2 °C); pH probe calibration; level and pressure sensors; safety valve tested (safety); heating and cooling gradient test; photo after service.

### 9.8 Stenter — Monthly PM

`PM-STENTER-MONTHLY`, 120 minutes, 12 items. Isolate, cool and lock-out (safety); chain, clips and pins inspected and lubricated; chain track alignment and wear; burner flame and gas train leak check (safety); exhaust and circulation fans with filters; chamber temperature against set point (±5 °C); padder rollers; selvedge uncurler and guider; weft straightener; overfeed and width setting; emergency stops and guards (safety); gas leak detector tested (safety).

### 9.9 Laboratory Instruments — Quarterly Calibration

`CAL-LAB-QUARTERLY`, 60 minutes, 9 items, maintenance type `CALIBRATION`. A spectrophotometer out of calibration passes shades the buyer's lab then rejects, which is a maintenance failure with a container-sized invoice behind it. Instrument cleaned and warmed up; calibration standard in date; white and black calibration; reference tile within tolerance (ΔE 0–1); light source hours; pH buffer calibration at 4, 7 and 10; balance against a reference weight; certificate updated; label applied with the next due date.

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
