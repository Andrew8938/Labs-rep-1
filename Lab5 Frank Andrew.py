sample_list = [10, 20, 30, 40, 50]
reversed_list = sample_list[::-1]
print("1. Обратный список:", reversed_list)

def list_sort(lst):
    return sorted(lst, key=abs, reverse=True)

numbers = [3, -7, 1, -15, 4, 0]
sorted_numbers = list_sort(numbers)
print("2. Сортировка по убыванию абсолютного значения:", sorted_numbers)

def change(lst):
    if len(lst) >= 2:
        lst[0], lst[-1] = lst[-1], lst[0]
    return lst

original_list = [9, 2, 5, 6, 1]
changed_list = change(original_list.copy())
print("3. Список после смены первого и последнего элемента:", changed_list)