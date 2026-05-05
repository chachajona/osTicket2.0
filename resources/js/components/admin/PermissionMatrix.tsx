import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export interface Permission {
    id: string;
    name: string;
    description?: string;
}

export interface PermissionGroup {
    id: string;
    name: string;
    permissions: Permission[];
}

interface PermissionMatrixProps {
    groups: PermissionGroup[];
    selectedPermissions: string[];
    onChange: (selected: string[]) => void;
}

export function PermissionMatrix({ groups, selectedPermissions, onChange }: PermissionMatrixProps) {
    const handleToggle = (permissionId: string, checked: boolean) => {
        if (checked) {
            onChange([...selectedPermissions, permissionId]);
        } else {
            onChange(selectedPermissions.filter((id) => id !== permissionId));
        }
    };

    const toggleGroup = (group: PermissionGroup, checked: boolean) => {
        const groupPermIds = group.permissions.map((p) => p.id);
        if (checked) {
            const newSelected = new Set([...selectedPermissions, ...groupPermIds]);
            onChange(Array.from(newSelected));
        } else {
            onChange(selectedPermissions.filter((id) => !groupPermIds.includes(id)));
        }
    };

    return (
        <div className="space-y-8">
            {groups.map((group) => {
                const groupPermIds = group.permissions.map((p) => p.id);
                const allSelected = groupPermIds.every((id) => selectedPermissions.includes(id));

                return (
                    <div key={group.id} className="space-y-4">
                        <div className="flex items-center gap-3 border-b border-slate-100 pb-2">
                            <Checkbox
                                id={`group-${group.id}`}
                                checked={allSelected}
                                onCheckedChange={(checked) => toggleGroup(group, checked === true)}
                            />
                            <Label htmlFor={`group-${group.id}`} className="text-base font-semibold text-slate-900 cursor-pointer">
                                {group.name}
                            </Label>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pl-7">
                            {group.permissions.map((permission) => (
                                <div key={permission.id} className="flex items-start space-x-3">
                                    <Checkbox
                                        id={`perm-${permission.id}`}
                                        checked={selectedPermissions.includes(permission.id)}
                                        onCheckedChange={(checked) => handleToggle(permission.id, checked === true)}
                                        className="mt-1"
                                    />
                                    <div className="space-y-1 leading-none">
                                        <Label
                                            htmlFor={`perm-${permission.id}`}
                                            className="text-sm font-medium text-slate-700 cursor-pointer"
                                        >
                                            {permission.name}
                                        </Label>
                                        {permission.description && (
                                            <p className="text-xs text-slate-500">
                                                {permission.description}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export default PermissionMatrix;
