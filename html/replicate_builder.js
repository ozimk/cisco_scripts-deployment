function build(json, template_files, organisation) {
    let replication_form = document.getElementById("topology");

    let vrfs_container = CreateFieldContainer("VRFs", replication_form, 'vrf');
    CreateButton(function () { AddVRF(null, null, vrfs_container, template_files); }, "Add New VRF", replication_form, "vrf-add", 'item-add');
    CreateButton(function () { RemoveVRF(vrfs_container); }, 'Remove VRF', replication_form, "vrf-remove", 'item-remove');

    let replication_container = document.createElement("fieldset");
    replication_container.className = 'field-container';
    CreateText("Replicate", "legend", replication_container, 'field-title');
    replication_form.appendChild(replication_container);

    AddReplicationField(organisation, replication_container);

    if (json != null) {
        for (let i in json["VRF"]) {
            AddVRF(i, json["VRF"][i], vrfs_container, template_files);
        }
        LoadReplication(json);
    }
}

function AddReplicationField(organisation, replication_container) {
    let room_container = document.createElement("fieldset");
    room_container.className = 'item-fieldset';
    room_container.id = `replicate-room`;
    room_container.name = `replicate[Room]`;
    CreateText("Rooms", "legend", room_container, 'item-legend');

    let rack_container = document.createElement("fieldset");
    rack_container.className = 'item-fieldset';
    rack_container.id = `replicate-rack`;
    rack_container.name = `replicate[Rack]`;
    rack_container.style.display = "flex"; /// style should be in css
    CreateText("Racks", "legend", rack_container, 'item-legend');

    let kit_container = document.createElement("fieldset");
    kit_container.className = 'item-fieldset';
    kit_container.id = `replicate-kit`;
    kit_container.name = `replicate[Kit]`;
    kit_container.style.display = "flex";
    CreateText("Kits", "legend", kit_container, 'item-legend');

    let router_container = document.createElement("fieldset");
    router_container.className = 'item-fieldset'
    router_container.id = `replicate-router`
    router_container.name = `replicate[Router]`
    CreateText("Routers", "legend", router_container, 'item-legend');

    let switch_container = document.createElement("fieldset");
    switch_container.className = 'item-fieldset';
    switch_container.id = `replicate-switch`
    switch_container.name = `replicate[Switch]`
    CreateText("Switches", "legend", switch_container, 'item-legend');

    replication_container.appendChild(room_container);

    AddList(organisation["Rooms"], room_container, "rack");

    for (i in organisation["Rooms"]) {
        let room = organisation["Rooms"][i];
        CreateCheckBox(`Racks for ${room}`, replication_container, `rack-${room}`, function () {
            UpdateList(organisation["Racks"], "kit", `rack-${room}`, rack_container, `${rack_container.id}-${room}`, `${rack_container.name}[${room}]`, `Racks in ${room}`)
        });

        UpdateCheckBox(`${room_container.id}-${i}`, `rack-${room}`);
    }
    CreateCheckBox(`Racks for All Rooms`, replication_container, `rack-All`, function () {
        UpdateList(organisation["Racks"], "kit", `rack-All`, rack_container, `${rack_container.id}-All`, `${rack_container.name}[All]`, `Racks in all Rooms`)
    });


    replication_container.appendChild(rack_container);

    for (i in organisation["Racks"]) {
        let rack = organisation["Racks"][i];
        CreateCheckBox(`Kits for ${rack} rack`, replication_container, `kit-${rack}`, function () {
            UpdateList(organisation["Kits"], "", `kit-${rack}`, kit_container, `${kit_container.id}-${rack}`, `${kit_container.name}[${rack}]`, `Kits in ${rack} Racks`)
        });
        UpdateCheckBox(`${rack_container.id}-${i}`, `kit-${rack}`);
    }
    CreateCheckBox(`Kits in All Racks`, replication_container, `kit-All`, function () {
        UpdateList(organisation["Kits"], "", `kit-All`, kit_container, `${kit_container.id}-All`, `${kit_container.name}[All]`, `Kits in all Racks`)
    });

    replication_container.appendChild(kit_container);

    AddList(organisation["Routers"], router_container, "");
    replication_container.appendChild(router_container);

    AddList(organisation["Switches"], switch_container, "");
    replication_container.appendChild(switch_container);

}

function AddList(items, container, next_id) {
    for (let i = 0; i < items.length; i++) {
        let div;
        if (next_id != "") {
            div = CreateCheckBox(items[i], container, i, function () { UpdateCheckBox(`${container.id}-${items[i].toLowerCase()}`, `${next_id}-${items[i]}`) });
        } else {
            div = CreateCheckBox(items[i], container, i);
        }
        CreateButton(function () { UpdateItem(div, -1); }, "^", div, null, 'item-move');
        CreateButton(function () { UpdateItem(div, 1); }, "v", div, null, 'item-move');

    }


}

function LoadReplication(json) {
    let event = new Event("change");

    if (json["Rooms"] != null) {
        LoadList(json["Rooms"]["Room"], "room");
    }

    if (json["Racks"] != null) {
        for (let room in json["Racks"]) {
            let container_check = document.getElementById(`rack-${room}`);
            container_check.checked = true;
            container_check.dispatchEvent(event);
            LoadList(json["Racks"][room], `rack-${room}`);
        }
    }

    if (json["Kits"] != null) {
        for (let rack in json["Kits"]) {
            let container_check = document.getElementById(`kit-${rack}`);
            container_check.checked = true;
            container_check.dispatchEvent(event);
            LoadList(json["Kits"][rack], `kit-${rack}`);
        }
    }

    if (json["Routers"] != null) {
        LoadList(json["Routers"]["Router"], "router");
    }

    if (json["Switches"] != null) {
        LoadList(json["Switches"]["Switch"], "switch");
    }


}

function LoadList(list, group) {
    let event = new Event("change");
    for (let i in list) {
        let item = list[i];
        let checkbox = document.getElementById(`replicate-${group}-${item.toLowerCase()}`);
        checkbox.checked = true;
        let move_by = Number(checkbox.name[checkbox.name.length - 2]) - i;
        for (let j = 0; j < Math.abs(move_by); j++) {
            direction = 0;
            if (move_by < 0) {
                direction = 1;
            } else if (move_by > 0) {
                direction = -1;
            } else {
                break;
            }
            UpdateItem(checkbox.parentElement, direction);

        }

        checkbox.dispatchEvent(event);
    }
}

////////////////////////////////////////////////////////////////
// VRF
///////////////////////////////////////////////////////////////
function AddVRF(name, template_file, container, template_files) {
    let index = container.children.length;
    let div = document.createElement("div");
    container.appendChild(div);

    let vrf_input = document.createElement("input");
    vrf_input.id = `vrf-${index}-vrf`;
    vrf_input.name = `vrf[${index}][vrf]`;
    vrf_input.addEventListener("change", function () { CheckErrorVRF(container, vrf_input); });
    let file_input = document.createElement("select");
    file_input.id = `vrf-${index}-file`;
    file_input.name = `vrf[${index}][file]`;
    for (let i = 0; i < template_files.length - 2; i++) {
        let option = document.createElement("option");
        option.value = template_files[i];
        option.textContent = template_files[i];
        if (template_files[i] == template_file) {
            option.selected = true;
        }
        file_input.appendChild(option);
    }
    if (name != null && template_file != null) { vrf_input.value = name; file_input.value = template_file; }
    div.appendChild(vrf_input);
    div.appendChild(file_input);
}

function RemoveVRF(vrf_container) {
    let lastChild = vrf_container.lastChild;
    vrf_container.removeChild(lastChild);
}

////////////////////////////////////////////////////////////
// Update Helpers
////////////////////////////////////////////////////////////

function UpdateList(items, next_id, check_box_id, list_container, id, name, title) {
    let check_box = document.getElementById(check_box_id);
    if (check_box.checked) {
        div = document.createElement("div");
        div.className = 'container';
        list_container.appendChild(div);
        div.style.display = "inline";
        div.id = id;
        div.name = name;
        CreateText(title, "label", div);
        AddList(items, div, next_id);
    } else {
        list_container.removeChild(document.getElementById(id));
    }
}

function UpdateItem(item, direction) {
    let container = item.parentElement;

    if (direction === -1 && item.previousElementSibling && item.previousElementSibling.children[1]) {
        let temp = item.previousElementSibling.children[1].name;
        item.previousElementSibling.children[1].name = item.children[1].name;
        item.children[1].name = temp;
        container.insertBefore(item, item.previousElementSibling);

    } else if (direction === 1 && item.nextElementSibling && item.nextElementSibling.children[1]) {
        let temp = item.nextElementSibling.children[1].name;
        item.nextElementSibling.children[1].name = item.children[1].name;
        item.children[1].name = temp;
        container.insertBefore(item, item.nextElementSibling.nextElementSibling)

    }
}



function UpdateCheckBox(caller_check_box_id, check_box_id) {
    let caller_check_box = document.getElementById(caller_check_box_id);
    let check_box = document.getElementById(check_box_id);
    if (caller_check_box != null && caller_check_box.checked) {
        check_box.parentElement.style.display = "block";
    } else {
        check_box.parentElement.style.display = "none";
        if (check_box.checked) {
            check_box.checked = false;
            let event = new Event("change");
            check_box.dispatchEvent(event);
        }


    }
}
//////////////////////////////////////////////////////////
// Create Helper
//////////////////////////////////////////////////////////

function CreateCheckBox(text, container, index, callback) {
    let div = document.createElement("div");
    let check_label = document.createElement("label");
    check_label.textContent = text;
    let check_box = document.createElement("input");
    check_box.type = "checkbox";
    check_box.name = `${container.name}[${index}]`
    check_box.value = text;
    if (container.id == "") { check_box.id = index } else { check_box.id = `${container.id}-${text.toLowerCase()}` }
    check_box.addEventListener("change", callback);
    check_label.for = check_box.id;
    div.appendChild(check_label);
    div.appendChild(check_box);
    container.appendChild(div);
    return div;
}

//////////////////////////////////////////////////////////
// Error Checks
///////////////////////////////////////////////////////////
function CheckErrorVRF(vrf_container, vrf_input) {
    const number = vrf_input.value;

    const clash = Array.from(vrf_container.children).some(child => {
        const isCurrentInput = child.firstChild.id === vrf_input.id;
        const isClash = !isCurrentInput && child.firstChild.value == number;

        child.firstChild.style.backgroundColor = isClash ? 'red' : 'white';

        return isClash;
    });

    vrf_input.style.backgroundColor = clash ? 'red' : 'white';
}