function build(json) {
    let physical_form = document.getElementById("topology");
    let device_container = CreateFieldContainer("Devices", physical_form, 'device');


    if (json != null) {

        for (let item in json) {
            if (item == "Connections") {
                continue;
            } else {
                AddDevice(item, device_container, json[item]["Interfaces"])
            }
        }
        if (json["Connections"]["Con"] != null) {
            for (let con of json["Connections"]["Con"]) {
                LoadCon(con);
            }
        }
    }

    // create the Device Add And Remove Buttons
    button_container = document.getElementById("addrem_container");
    CreateButton(() => { AddDevice(null, device_container, null) }, "Add New Device", button_container, null, 'item-add');
    CheckErrorInterface();
}

////////////////////////////////////////////////////////////////
// Devices
////////////////////////////////////////////////////////////////
function AddDevice(name, physical_form, interfaces) {
    if (name == null) {
        name = prompt("Enter Name of Device", `Router ${physical_form.children.length + 1}`) ?? ""; // there are 4 children at start
    }
    name = name.trim();
    if (name == "" || CheckErrorDeviceName(name, physical_form)) { return; }

    //Create the Device Fieldset
    let device_field = ConfigFieldset(physical_form, 'device', name, RemoveDevice);

    //Createt the Interfaces Menu
    let interface_container = CreateIndexedContainer(device_field, "Interfaces (direction | name): ", (interface_container) => { AddInterface(null, null, interface_container) }, `${device_field.id}-interfaces`, `${device_field.name}[Interfaces]`);

    for (let i in interfaces) {
        AddInterface(i, interfaces[i], interface_container);
    }
}

function RemoveDevice(device_field) {

    let remove = confirm("Remove Device?");
    if (!remove) { return; }
    for (interface_div of Array.from(document.getElementById(`${device_field.id}-interfaces`).children)) {
        console.log(interface_div);
        RemoveInterface(interface_div, false);
    }
    device_field.parentElement.removeChild(device_field);
}

function AddInterface(direction, name, container) {
    //Create Single INterface Container
    let div = CreateContainer(container, `${container.id}-${container.index}`, `${container.name}[${container.index}]`);
    container.index++;
    div.connected = false;

    CreateButton(() => { RemoveInterface(div) }, "-", div, null, 'item-remove');

    //Direction Input
    let dir_input = CreateInput("number", div, `${div.id}-direction`, `${div.name}[direction]`, 'direction-input');
    if (direction != null) { dir_input.value = direction; }
    dir_input.addEventListener('input', function () { CheckErrorInterface() });

    //Name Input
    let name_input = CreateInput("text", div, `${div.id}-name`, `${div.name}[name]`, 'name-input');
    if (name != null) { name_input.value = name; }
    name_input.addEventListener('input', () => {
        CheckErrorInterface();
    });

    container.appendChild(div);
    CreateConnectButton(div, true, function () { Connect(div, null) });
}

function RemoveInterface(interface_div, ask = true) {

    if (ask) {
        let remove = confirm("Remove Interface?");
        if (!remove) { return; }
    }

    if (interface_div.connected) { // If it is connected need to disconnect the other side.
        let device_to = document.getElementById(`${interface_div.id}-device_connection`).value;
        let interface_to = document.getElementById(`${interface_div.id}-interface_connection`).value;
        if (device_to == null || device_to.trim() == "" || interface_to == null | interface_to.trim() == "") { return; }

        parent_to = document.getElementById(`device-${device_to}-interfaces`);
        let div_to = null;
        if (parent_to == null) { return; }
        for (child of Array.from(parent_to.children)) {
            let direction_input = document.getElementById(`${child.id}-direction`);
            if (direction_input == null) { continue; }
            if (direction_input.value == interface_to) {
                div_to = child;
                break;
            }
        }

        if (div_to != null && div_to.connected) {
            Disconnect(interface_div, div_to)
        }
    }

    interface_div.parentElement.removeChild(interface_div);
    CheckErrorInterface();
}

/////////////////////////////////////////////////////////////////////////////
// Connections
/////////////////////////////////////////////////////////////////////////////
function Connect(div_from, div_to) {
    if (div_from != null) {

        //Create To Inputs
        div_from.connected = true;
        let device_to_input = CreateInput("text", div_from, `${div_from.id}-device_connection`, `${div_from.name}[device_connection]`, 'name-input');
        device_to_input.placeholder = "Device..."
        device_to_input.addEventListener("change", function () { Connect(null, div_from) });

        let interface_to_input = CreateInput("number", div_from, `${div_from.id}-interface_connection`, `${div_from.name}[interface_connection]`, 'direction-input')
        interface_to_input.setAttribute("style", "width:35px");
        interface_to_input.placeholder = 0;
        interface_to_input.addEventListener("change", function () { Connect(null, div_from) });

        check_div = document.createElement("span");
        check_div.id = `${div_from.id}-extint`;
        div_from.appendChild(check_div);

        CreateRadioButton(check_div, `${div_from.name}[extint]`, `${div_from.id}-in`, "Internal", "in");
        CreateRadioButton(check_div, `${div_from.name}[extint]`, `${div_from.id}-next`, "To Next Kit", "next");
        CreateRadioButton(check_div, `${div_from.name}[extint]`, `${div_from.id}-prev`, "To Previous Kit", "prev");
        check_div.firstChild.checked = true;

        //Change Button to disconnect
        CreateConnectButton(div_from, false, function () { Disconnect(div_from, null) });

    } else if (div_to != null) {
        let device_from = document.getElementById(`${div_to.id}-device_connection`).value;
        let interface_from = document.getElementById(`${div_to.id}-interface_connection`).value;
        if (device_from == null || device_from.trim() == "" || interface_from == null || interface_from.trim() == "") { return; }

        parent_from = document.getElementById(`device-${device_from}-interfaces`);
        if (parent_from == null) { alert(`Device ${device_from} does not exist`); Disconnect(null, div_to); return; }
        for (child of Array.from(parent_from.children)) {
            let direction_input = document.getElementById(`${child.id}-direction`);
            if (direction_input == null) { continue; }
            if (direction_input.value == interface_from) {
                div_from = child;
                break;
            }
        }

        if (div_from == null) { alert(`Interface ${interface_from} does not exist on ${device_from}`); Disconnect(null, div_to); return; }
        if (div_from.connected) { alert(`Interface ${interface_from} on ${device_from} has an open connection`); Disconnect(null, div_to); return; }

        // create inputs for from_div
        div_from.connected = true;
        let device_to_input = CreateInput("text", div_from, `${div_from.id}-device_connection`, `${div_from.name}[device_connection]`, 'name-input');
        device_to_input.value = document.getElementById(`${div_to.parentElement.parentElement.id}`).id.split("-")[1];
        device_to_input.addEventListener("change", function () { Connect(null, div_from) });

        let interface_to_input = CreateInput("number", div_from, `${div_from.id}-interface_connection`, `${div_from.name}[interface_connection]`, 'direction-input');
        interface_to_input.value = document.getElementById(`${div_to.id}-direction`).value;
        interface_to_input.addEventListener("change", function () { Connect(null, div_from) })

        //both are now connected
        device_to_input.readOnly = true;
        interface_to_input.readOnly = true;
        document.getElementById(`${div_to.id}-device_connection`).readOnly = true;
        document.getElementById(`${div_to.id}-interface_connection`).readOnly = true;

        //create radio buttons
        check_div = document.createElement("span");
        check_div.id = `${div_from.id}-extint`;
        div_from.appendChild(check_div);

        CreateRadioButton(check_div, `${div_from.name}[extint]`, `${div_from.id}-in`, "Internal", "in");
        CreateRadioButton(check_div, `${div_from.name}[extint]`, `${div_from.id}-next`, "To Next Kit", "next");
        CreateRadioButton(check_div, `${div_from.name}[extint]`, `${div_from.id}-prev`, "To Previous Kit", "prev");
        check_div.firstChild.checked = true;

        //setup events for radiobuttons
        AttachRadioButtonConnectionEvents(div_from, div_to, `${div_from.id}-in`)
        AttachRadioButtonConnectionEvents(div_from, div_to, `${div_from.id}-prev`)
        AttachRadioButtonConnectionEvents(div_from, div_to, `${div_from.id}-next`)
        AttachRadioButtonConnectionEvents(div_to, div_from, `${div_to.id}-in`);
        AttachRadioButtonConnectionEvents(div_to, div_from, `${div_to.id}-prev`);
        AttachRadioButtonConnectionEvents(div_to, div_from, `${div_to.id}-next`);
        UpdateIntExt(div_to, div_from);
        //fix buttons so they will delete both connections
        CreateConnectButton(div_from, false, function () { Disconnect(div_from, div_to) });
        CreateConnectButton(div_to, false, function () { Disconnect(div_from, div_to) });

        //set up event listeners on the direciton so that the links update correctly
        let from_direction_input = document.getElementById(`${div_from.id}-direction`);
        from_direction_input.addEventListener('change', function () { UpdateDirectionConnection(div_from, div_to); });
        let to_direction_input = document.getElementById(`${div_to.id}-direction`);
        to_direction_input.addEventListener('change', function () { UpdateDirectionConnection(div_to, div_from); });
    }
}

function Disconnect(div_from, div_to) {
    if (div_from != null) {
        CreateConnectButton(div_from, true, function () { Connect(div_from, null) })
        div_from.connected = false;

        let from_interface_input = document.getElementById(`${div_from.id}-interface_connection`);
        div_from.removeChild(from_interface_input);
        let from_device_input = document.getElementById(`${div_from.id}-device_connection`);
        div_from.removeChild(from_device_input);
        let from_check_div = document.getElementById(`${div_from.id}-extint`);
        div_from.removeChild(from_check_div);


    }

    if (div_to != null) {
        CreateConnectButton(div_to, true, function () { Connect(div_to, null) })
        div_to.connected = false;

        let to_interface_input = document.getElementById(`${div_to.id}-interface_connection`);
        div_to.removeChild(to_interface_input);
        let to_device_input = document.getElementById(`${div_to.id}-device_connection`);
        div_to.removeChild(to_device_input);
        let to_check_div = document.getElementById(`${div_to.id}-extint`);
        div_to.removeChild(to_check_div);
    }
}


function LoadCon(con) {

    let parts = con.split(":");
    let parent_div_from = document.getElementById(`device-${parts[0]}-interfaces`);
    let div_from;
    for (child of Array.from(parent_div_from.children)) {
        let direction_input = document.getElementById(`${child.id}-direction`);
        if (direction_input == null) { continue; }
        if (direction_input.value == parts[1]) {
            div_from = child;
            break;
        }
    }

    if (div_from == null) return;
    if (!(div_from.connected)) {
        Connect(div_from, null);
        let checked_input = document.getElementById(`${div_from.id}-${parts[4]}`);
        checked_input.checked = true;
        let device_input = document.getElementById(`${div_from.id}-device_connection`);
        device_input.value = parts[2];
        let interface_input = document.getElementById(`${div_from.id}-interface_connection`);
        interface_input.value = parts[3];

        let event = new Event("change");
        interface_input.dispatchEvent(event);

    }
}

///////////////////////////////////////////////////////////////////////////
// Update Functions
//////////////////////////////////////////////////////////////////////////
function UpdateIntExt(div_from, div_to) {
    selected = "";
    if (document.getElementById(`${div_from.id}-in`).checked) { selected = 'in'; }
    else if (document.getElementById(`${div_from.id}-next`).checked) { selected = 'next'; }
    else if (document.getElementById(`${div_from.id}-prev`).checked) { selected = 'prev'; }
    switch (selected) {
        case 'in':
            document.getElementById(`${div_to.id}-in`).checked = true;
            break;
        case 'next':
            document.getElementById(`${div_to.id}-prev`).checked = true;
            break;
        case 'prev':
            document.getElementById(`${div_to.id}-next`).checked = true;
            break;
    }
}

function UpdateDirectionConnection(div_from, div_to) {

    if (div_from.connected && div_to.connected) {
        interface_input = document.getElementById(`${div_to.id}-interface_connection`);
        direction_input = document.getElementById(`${div_from.id}-direction`);
        interface_input.value = direction_input.value;
    }

}

//////////////////////////////////////////////////////////////////////////////
// Create Helpers
/////////////////////////////////////////////////////////////////////////////


function AttachRadioButtonConnectionEvents(div_from, div_to, check_id) {
    let from_in_check = document.getElementById(check_id);
    from_in_check.addEventListener("change", function () { UpdateIntExt(div_from, div_to) });
}





///////////////////////////////////////////////////////////////////
// Error Checking
///////////////////////////////////////////////////////////////////
function CheckErrorDeviceName(name, container) {
    for (child of container.children) {
        if (child.id == `device-${name}`) {
            return true;
        }
    }

    return false;
}

function CheckErrorInterface(interface_container, name_input) {

    for (let device of Array.from(document.getElementById("device").children)) {
        let interfaces = document.getElementById(`${device.id}-interfaces`);
        for (let interface of Array.from(interfaces.children)) {
            let dir_input = document.getElementById(`${interface.id}-direction`);
            let name_input = document.getElementById(`${interface.id}-name`);

            let number = dir_input.value;
            let clash_dir = Array.from(interfaces.children).some(child => {
                let childInput = document.getElementById(`${child.id}-direction`);
                if (childInput.id === dir_input.id) return false;
                return childInput.value === number;
            });

            let big = number > 99;

            let name = name_input.value;
            let clash_name = Array.from(interfaces.children).some(child => {
                let childInput = document.getElementById(`${child.id}-name`);
                if (childInput.id === name_input.id) return false;
                return childInput.value === name;
            });
            name_input.style.backgroundColor = clash_name ? "red" : "white";
            name_input.title = clash_name ? "This interface name already exists on this device" : "";


            dir_input.style.backgroundColor = !clash_dir && !big ? "white" : "red";
            let error = "";
            if (clash_dir) {
                error += "This direction already exists on this model. "
            }
            if (big) {
                error += "The value should be less than 100";
            }
            dir_input.title = error;
        }

    }






}